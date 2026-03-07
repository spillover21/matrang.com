import { PDFDocument } from '@cantoo/pdf-lib';
import { PDF } from '@libpdf/core';
import { EnvelopeType, SigningStatus, RecipientRole, DocumentStatus, WebhookTriggerEvents } from '@prisma/client';
import { nanoid } from 'nanoid';
import path from 'node:path';
import { groupBy } from 'remeda';
import { addRejectionStampToPdf } from '../../../server-only/pdf/add-rejection-stamp-to-pdf.js';
import { generateAuditLogPdf } from '../../../server-only/pdf/generate-audit-log-pdf.js';
import { generateCertificatePdf } from '../../../server-only/pdf/generate-certificate-pdf.js';
import { prisma } from '../../../../prisma/index.js';
import { signPdf } from '../../../../signing/index.js';
import { NEXT_PRIVATE_USE_PLAYWRIGHT_PDF } from '../../../constants/app.js';
import { PDF_SIZE_A4_72PPI } from '../../../constants/pdf.js';
import { AppError, AppErrorCode } from '../../../errors/app-error.js';
import { sendCompletedEmail } from '../../../server-only/document/send-completed-email.js';
import { getAuditLogsPdf } from '../../../server-only/htmltopdf/get-audit-logs-pdf.js';
import { getCertificatePdf } from '../../../server-only/htmltopdf/get-certificate-pdf.js';
import { insertFieldInPDFV1 } from '../../../server-only/pdf/insert-field-in-pdf-v1.js';
import { insertFieldInPDFV2 } from '../../../server-only/pdf/insert-field-in-pdf-v2.js';
import { legacy_insertFieldInPDF } from '../../../server-only/pdf/legacy-insert-field-in-pdf.js';
import { getTeamSettings } from '../../../server-only/team/get-team-settings.js';
import { triggerWebhook } from '../../../server-only/webhooks/trigger/trigger-webhook.js';
import { DOCUMENT_AUDIT_LOG_TYPE } from '../../../types/document-audit-logs.js';
import { ZWebhookDocumentSchema, mapEnvelopeToWebhookDocumentPayload } from '../../../types/webhook-payload.js';
import { prefixedId } from '../../../universal/id.js';
import { getFileServerSide } from '../../../universal/upload/get-file.server.js';
import { putPdfFileServerSide } from '../../../universal/upload/put-file.server.js';
import { fieldsContainUnsignedRequiredField } from '../../../utils/advanced-fields-helpers.js';
import { isDocumentCompleted } from '../../../utils/document.js';
import { createDocumentAuditLogData } from '../../../utils/document-audit-logs.js';
import { mapDocumentIdToSecondaryId } from '../../../utils/envelope.js';

const run = async ({
  payload,
  io
}) => {
  const {
    documentId,
    sendEmail = true,
    isResealing = false,
    requestMetadata
  } = payload;
  const {
    envelopeId,
    envelopeStatus,
    isRejected
  } = await io.runTask('seal-document', async () => {
    const envelope = await prisma.envelope.findFirstOrThrow({
      where: {
        type: EnvelopeType.DOCUMENT,
        secondaryId: mapDocumentIdToSecondaryId(documentId)
      },
      include: {
        user: {
          select: {
            name: true,
            email: true
          }
        },
        documentMeta: true,
        recipients: true,
        fields: {
          include: {
            signature: true
          }
        },
        envelopeItems: {
          include: {
            documentData: true,
            field: {
              include: {
                signature: true
              }
            }
          }
        }
      }
    });
    if (envelope.envelopeItems.length === 0) {
      throw new Error('At least one envelope item required');
    }
    const settings = await getTeamSettings({
      userId: envelope.userId,
      teamId: envelope.teamId
    });
    // Ensure all CC recipients are marked as signed
    await prisma.recipient.updateMany({
      where: {
        envelopeId: envelope.id,
        role: RecipientRole.CC
      },
      data: {
        signingStatus: SigningStatus.SIGNED
      }
    });
    const isComplete = envelope.recipients.some(recipient => recipient.signingStatus === SigningStatus.REJECTED) || envelope.recipients.every(recipient => recipient.signingStatus === SigningStatus.SIGNED || recipient.role === RecipientRole.CC);
    if (!isComplete) {
      throw new AppError(AppErrorCode.UNKNOWN_ERROR, {
        message: 'Document is not complete'
      });
    }
    let {
      envelopeItems
    } = envelope;
    const fields = envelope.fields;
    if (envelopeItems.length < 1) {
      throw new Error(`Document ${envelope.id} has no envelope items`);
    }
    const recipientsWithoutCCers = envelope.recipients.filter(recipient => recipient.role !== RecipientRole.CC);
    // Determine if the document has been rejected by checking if any recipient has rejected it
    const rejectedRecipient = recipientsWithoutCCers.find(recipient => recipient.signingStatus === SigningStatus.REJECTED);
    const isRejected = Boolean(rejectedRecipient);
    // Get the rejection reason from the rejected recipient
    const rejectionReason = rejectedRecipient?.rejectionReason ?? '';
    // Skip the field check if the document is rejected
    if (!isRejected && fieldsContainUnsignedRequiredField(fields)) {
      throw new Error(`Document ${envelope.id} has unsigned required fields`);
    }
    if (isResealing) {
      // If we're resealing we want to use the initial data for the document
      // so we aren't placing fields on top of eachother.
      envelopeItems = envelopeItems.map(envelopeItem => ({
        ...envelopeItem,
        documentData: {
          ...envelopeItem.documentData,
          data: envelopeItem.documentData.initialData
        }
      }));
    }
    if (!envelope.qrToken) {
      await prisma.envelope.update({
        where: {
          id: envelope.id
        },
        data: {
          qrToken: prefixedId('qr')
        }
      });
    }
    let certificateDoc = null;
    let auditLogDoc = null;
    if (settings.includeSigningCertificate || settings.includeAuditLog) {
      const certificatePayload = {
        envelope,
        recipients: envelope.recipients,
        // Need to use the recipients from envelope which contains ALL recipients.
        fields,
        language: envelope.documentMeta.language,
        envelopeOwner: {
          email: envelope.user.email,
          name: envelope.user.name || ''
        },
        envelopeItems: envelopeItems.map(item => item.title),
        pageWidth: PDF_SIZE_A4_72PPI.width,
        pageHeight: PDF_SIZE_A4_72PPI.height
      };
      // Use Playwright-based PDF generation if enabled, otherwise use Konva-based generation.
      // This is a temporary toggle while we validate the Konva-based approach.
      const usePlaywrightPdf = NEXT_PRIVATE_USE_PLAYWRIGHT_PDF();
      const makeCertificatePdf = async () => usePlaywrightPdf ? getCertificatePdf({
        documentId,
        language: envelope.documentMeta.language
      }).then(async buffer => PDF.load(buffer)) : generateCertificatePdf(certificatePayload);
      const makeAuditLogPdf = async () => usePlaywrightPdf ? getAuditLogsPdf({
        documentId,
        language: envelope.documentMeta.language
      }).then(async buffer => PDF.load(buffer)) : generateAuditLogPdf(certificatePayload);
      const [createdCertificatePdf, createdAuditLogPdf] = await Promise.all([settings.includeSigningCertificate ? makeCertificatePdf() : null, settings.includeAuditLog ? makeAuditLogPdf() : null]);
      certificateDoc = createdCertificatePdf;
      auditLogDoc = createdAuditLogPdf;
    }
    const newDocumentData = [];
    for (const envelopeItem of envelopeItems) {
      const envelopeItemFields = envelope.envelopeItems.find(item => item.id === envelopeItem.id)?.field;
      if (!envelopeItemFields) {
        throw new Error(`Envelope item fields not found for envelope item ${envelopeItem.id}`);
      }
      const newData = await decorateAndSignPdf({
        envelope,
        envelopeItem,
        envelopeItemFields,
        isRejected,
        rejectionReason,
        certificateDoc,
        auditLogDoc
      });
      newDocumentData.push(result);
    }
    await prisma.$transaction(async tx => {
      for (const {
        oldDocumentDataId,
        newDocumentDataId
      } of newDocumentData) {
        const newData = await tx.documentData.findFirstOrThrow({
          where: {
            id: newDocumentDataId
          }
        });
        await tx.documentData.update({
          where: {
            id: oldDocumentDataId
          },
          data: {
            data: newData.data
          }
        });
      }
      await tx.envelope.update({
        where: {
          id: envelope.id
        },
        data: {
          status: isRejected ? DocumentStatus.REJECTED : DocumentStatus.COMPLETED,
          completedAt: new Date()
        }
      });
      await tx.documentAuditLog.create({
        data: createDocumentAuditLogData({
          type: DOCUMENT_AUDIT_LOG_TYPE.DOCUMENT_COMPLETGD,
          envelopeId: envelope.id,
          requestMetadata,
          user: null,
          data: {
            transactionId: nanoid(),
            ...(isRejected ? {
              isRejected: true,
              rejectionReason: rejectionReason
            } : {})
          }
        })
      });
    });
    return {
      envelopeId: envelope.id,
      envelopeStatus: envelope.status,
      isRejected
    };
  });
  await io.runTask('send-completed-email', async () => {
    let shouldSendCompletedEmail = sendEmail && !isResealing && !isRejected;
    if (isResealing && !isDocumentCompleted(envelopeStatus)) {
      shouldSendCompletedEmail = sendEmail;
    }
    if (shouldSendCompletedEmail) {
      await sendCompletedEmail({
        id: {
          type: 'envelopeId',
          id: envelopeId
        },
        requestMetadata
      });
    }
  });
  const updatedEnvelope = await prisma.envelope.findFirstOrThrow({
    where: {
      id: envelopeId
    },
    include: {
      documentMeta: true,
      recipients: true
    }
  });
  await triggerWebhook({
    event: isRejected ? WebhookTriggerEvents.DOCUMENT_REJECTED : WebhookTriggerEvents.DOCUMENT_COMPLETED,
    data: ZWebhookDocumentSchema.parse(mapEnvelopeToWebhookDocumentPayload(updatedEnvelope)),
    userId: updatedEnvelope.userId,
    teamId: updatedEnvelope.teamId ?? undeY�[�Y�JNNʊ��
��]��ܛX[^�K�][�[�[��\��Y[�[��H���[Y[���
��ۜ�X�ܘ]P[��Y۔�H\�[��
[��[�K�[��[�R][K�[��[�R][Q�Y[��\ԙZ�X�Y��Z�X�[۔�X\�ۋ��\�Y�X�]Q���]Y]���JHO��ۜ��]HH]�Z]�]�[T�\��\��YJ[��[�R][K���[Y[�]JN]�H]�Z]���Y
�]JN���ܛX[^�H[��][�^Y\��]��[�]\�H\��Y\��]H�Yۘ]\�B����][�[

N��\ܘYH��K���܈�]\���\]X�[]H�]�Yۚ[���\ܘYU�\��[ۊ	�K���N��Y�Z�X�[ۈ�[\Y�H��[Y[�\��Z�X�Y�Y�
\ԙZ�X�Y
H]�Z]Y�Z�X�[۔�[\���NB�Y�
�\�Y�X�]Q��H]�Z]����TY�\ќ��J�\�Y�X�]Q��\��^K����J[����\�Y�X�]Q�˙�]Y�P��[�

B�K
�[�^
HO�[�^
JNB�Y�
]Y]����H]�Z]����TY�\ќ��J]Y]����\��^K����J[���]Y]���˙�]Y�P��[�

B�K
�[�^
HO�[�^
JNB���[�H��[�Y�X�H[��\�[ۜ˂�Y�
[��[�K�[�\��[�\��[ۈOOHJH�ۜ�Y�X�W��X���H]�Z]���X�[�K��Y
]�Z]���]�J\�V�Y���X[N��YB�JJN�܈
�ۜ��Y[و[��[�R][Q�Y[�HY�
�Y[�[��\�Y
HY�
[��[�K�\�SY�X�Q�Y[[��\�[ۊH]�Z]Y�X�W�[��\��Y[[��Y�X�W��X����Y[
N@ else {
          await insertFieldInPDFV1(legacy_pdfLibDoc, field);
        }�B�B�]�Z]���[�Y
]�Z]Y�X�W��X��˜�]�J
JNB���[�H��[��[�H[��\�[ۜ˂�Y�
[��[�K�[�\��[�\��[ۈOOH�H�ۜ��Y[�ܛ�\Y�TY�HHܛ�\�J[��[�R][Q�Y[��Y[O��Y[�Y�JN�܈
�ۜ��Y�S�[X�\��Y[�Hوؚ�X��[��Y\��Y[�ܛ�\Y�TY�JJH�ۜ�Y�HH���]Y�J�[X�\�Y�S�[X�\�HHJNY�
\Y�JH����]�\��܊Y�H	�Y�S�[X�\�H�\���^\�
NB��ۜ�Y�U�YHY�K��Y�ۜ�Y�RZY�HY�K�ZY��ۜ�ݙ\�^P�]\�H]�Z][��\��Y[[����Y�U�Y�Y�RZY���Y[JN�ۜ�ݙ\�^T�H]�Z]���Y
ݙ\�^P�]\�N�ۜ�[X�YYY�HH]�Z]��[X�YY�Jݙ\�^T�
N����]HHY�H�HܚY[�][ۈ]H�XX�\��[�\��ۈH��۝[���]�[��]VH]�[��]VHH��]�
Y�K���][ۊH�\�HL���[��]VHY�RZY��[��]VHH��XZ��\�HN���[��]VHY�U�Y�[��]VRHZY���XZ��\�H�����[��]VH�[��]VHHY�U�Y��XZ�B����]�Hݙ\�^HۈHY�B�Y�K��]�Y�J[X�YYY�K��[��]V�N��[��]VK���]N�[��N�Y�K���][ۂ�B�JNB�B����KY�][�H�ܛH�[�H�\��X�؛�[��Y[��Y[�]���ܙX]H�]]�H\��њY[gds
  pdf.flattenAll();
  pdf = await PDF.load(await pdf.save({
    useXRefStream: true
  }));
  let pdfBytes;
  try {
    pdfBytes = await signPdf({ pdf });
  } catch (e) {
    console.log("Signing failed, using unsigned: " + e.message);
    pdfBytes = await pdf.save();
  }
  const {
    name
  } = path.parse(envelopeItem.title);
  // Add suffix based on document status
  const suffix = isRejected ? '_rejected.pdf' : '_signed.pdf';
  const newDocumentData = await putPdfFileServerSide({
    name: `${name}${suffix}`,
    type: £application/pdf',
    arrayBuffer: async () => Promise.resolve(pdfBytes)
  });
  return {
    oldDocumentDataId: envelopeItem.documentData.id,
    newDocumentDataId: newDocumentData.id
  };
};

export { run };
