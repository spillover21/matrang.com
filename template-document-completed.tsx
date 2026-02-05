import { Trans } from '@lingui/react/macro';

import { Button, Column, Img, Section, Text } from '../components';
import { TemplateDocumentImage } from './template-document-image';

export interface TemplateDocumentCompletedProps {
  downloadLink: string;
  documentName: string;
  assetBaseUrl: string;
  customBody?: string;
}

export const TemplateDocumentCompleted = ({
  downloadLink,
  documentName,
  assetBaseUrl,
  customBody,
}: TemplateDocumentCompletedProps) => {
  const getAssetUrl = (path: string) => {
    return new URL(path, assetBaseUrl).toString();
  };

  return (
    <>
      <TemplateDocumentImage className="mt-6" assetBaseUrl={assetBaseUrl} />

      <Section>
        <Section className="mb-4">
          <Column align="center">
            <Text className="text-base font-semibold text-[#7AC455]">
              <Img
                src={getAssetUrl('/static/completed.png')}
                className="-mt-0.5 mr-2 inline h-7 w-7 align-middle"
              />
              <Trans>Completed</Trans>
            </Text>
          </Column>
        </Section>

        <Text className="text-primary mb-0 text-center text-lg font-semibold">
          {customBody || (
            <>
              <p>
                <Trans>“{documentName}” was signed by all signers</Trans>
              </p>
              <div style={{ marginTop: '20px', fontSize: '16px', lineHeight: '1.6', color: '#111827', textAlign: 'left', backgroundColor: '#f9f9f9', padding: '15px', borderRadius: '8px' }}>
                <p><strong>Здравствуйте!</strong></p>
                <p>Процесс подписания успешно завершен. К письму прикреплен ваш экземпляр договора.</p>
                <p>Данный файл содержит Лист Аудита, подтверждающий подлинность сделки.</p>
                <p>Мы рекомендуем сохранить этот файл.</p>
                <p style={{ marginTop: '10px' }}><strong>Поздравляем с приобретением будущего члена семьи!</strong></p>
                <br/>
                <p style={{ fontSize: '14px', color: '#666' }}>С уважением,<br/>Команда Matrang & Great Legacy Bully</p>

                <hr style={{ margin: '20px 0', borderTop: '1px solid #e5e7eb' }} />

                <p><strong>Hello!</strong></p>
                <p>The signing process has been successfully completed. Your copy of the agreement is attached to this email.</p>
                <p>This file includes the Audit Trail, which confirms the authenticity and digital integrity of the transaction.</p>
                <p>We recommend that you keep this document for your records.</p>
                <p style={{ marginTop: '10px' }}><strong>Congratulations on the new addition to your family!</strong></p>
              </div>
            </>
          )}
        </Text>

        <Text className="my-1 text-center text-base text-slate-400">
          <Trans>Continue by downloading the document.</Trans>
        </Text>

        <Section className="mb-6 mt-8 text-center">
          <Button
            className="rounded-lg border border-solid border-slate-200 px-4 py-2 text-center text-sm font-medium text-black no-underline"
            href={downloadLink}
          >
            <Img
              src={getAssetUrl('/static/download.png')}
              className="mb-0.5 mr-2 inline h-5 w-5 align-middle"
            />
            <Trans>Download</Trans>
          </Button>
        </Section>
      </Section>
    </>
  );
};

export default TemplateDocumentCompleted;
