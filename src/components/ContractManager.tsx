import { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Save, Send, Download, FileText, Trash2, Plus, Archive, Upload } from "lucide-react";
import { toast } from "sonner";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { PDFDocument } from 'pdf-lib';

// Version: 2026-01-26-v4-DEBUGGER
if (typeof window !== 'undefined') {
  (window as any).__CONTRACT_MANAGER_LOADED = Date.now();
  console.error("🚨 ContractManager module loaded:", new Date().toISOString());
}
interface ContractTemplate {
  id: number;
  name: string;
  data: ContractData;
  createdAt: string;
}

interface ContractData {
  // Данные питомника/заводчика
  kennelName: string;
  kennelOwner: string;
  kennelAddress: string;
  kennelPhone: string;
  kennelEmail: string;
  kennelPassportSeries?: string;
  kennelPassportNumber?: string;
  kennelPassportIssuedBy?: string;
  kennelPassportIssuedDate?: string;
  
  // Данные покупателя
  buyerName: string;
  buyerAddress: string;
  buyerPhone: string;
  buyerEmail: string;
  buyerPassportSeries?: string;
  buyerPassportNumber?: string;
  buyerPassportIssuedBy?: string;
  buyerPassportIssuedDate?: string;
  
  // Данные о родителях щенка
  dogFatherName?: string;
  dogFatherRegNumber?: string;
  dogMotherName?: string;
  dogMotherRegNumber?: string;
  
  // Данные о щенке
  dogName: string;
  dogBreed: string;
  dogBirthDate: string;
  dogGender: string;
  dogColor: string;
  dogChipNumber?: string;
  dogPuppyCard?: string;
  
  // Цель приобретения
  purposeBreeding?: boolean;
  purposeCompanion?: boolean;
  purposeGeneral?: boolean;
  
  // Финансовые условия
  price: string;
  depositAmount?: string;
  depositDate?: string;
  remainingAmount?: string;
  finalPaymentDate?: string;
  
  // Вакцинация
  dewormingDate?: string;
  vaccinationDates?: string;
  vaccineName?: string;
  nextDewormingDate?: string;
  nextVaccinationDate?: string;
  
  // Дополнительные условия
  specialFeatures?: string;
  deliveryTerms?: string;
  additionalAgreements?: string;
  recommendedFood?: string;
  
  // Дата и место договора
  contractDate: string;
  contractPlace?: string;
}

interface SignedContract {
  id: number;
  contractNumber: string;
  data: ContractData;
  createdAt: string;
  sentAt?: string;
  signedAt?: string;
  signedDocumentUrl?: string;
  adobeSignAgreementId?: string;
  status?: 'draft' | 'sent' | 'sent_by_email' | 'signed';
}

interface ContractManagerProps {
  token: string;
}

const ContractManager = ({ token }: ContractManagerProps) => {
  const [activeTab, setActiveTab] = useState("new");
  const [templates, setTemplates] = useState<ContractTemplate[]>([]);
  const [contracts, setContracts] = useState<SignedContract[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [sending, setSending] = useState(false);
  const [pdfTemplate, setPdfTemplate] = useState<string>("");
  const [pdfFieldInfo, setPdfFieldInfo] = useState<{ count: number; names: string[]; lastChecked?: string; error?: string }>({
    count: 0,
    names: []
  });
  const [buildVersion, setBuildVersion] = useState<string>("");
  
  const [formData, setFormData] = useState<ContractData>({
    // Данные питомника
    kennelName: "GREAT LEGACY BULLY",
    kennelOwner: "",
    kennelAddress: "",
    kennelPhone: "+7 (900) 455-27-16",
    kennelEmail: "greatlegacybully@gmail.com",
    kennelPassportSeries: "",
    kennelPassportNumber: "",
    kennelPassportIssuedBy: "",
    kennelPassportIssuedDate: "",
    
    // Данные покупателя
    buyerName: "",
    buyerAddress: "",
    buyerPhone: "",
    buyerEmail: "",
    buyerPassportSeries: "",
    buyerPassportNumber: "",
    buyerPassportIssuedBy: "",
    buyerPassportIssuedDate: "",
    
    // Родители щенка
    dogFatherName: "",
    dogFatherRegNumber: "",
    dogMotherName: "",
    dogMotherRegNumber: "",
    
    // Данные щенка
    dogName: "",
    dogBreed: "Американский булли",
    dogBirthDate: "",
    dogGender: "",
    dogColor: "",
    dogChipNumber: "",
    dogPuppyCard: "",
    
    // Цель приобретения
    purposeBreeding: false,
    purposeCompanion: false,
    purposeGeneral: false,
    
    // Финансовые условия
    price: "",
    depositAmount: "",
    depositDate: "",
    remainingAmount: "",
    finalPaymentDate: "",
    
    // Вакцинация
    dewormingDate: "",
    vaccinationDates: "",
    vaccineName: "",
    nextDewormingDate: "",
    nextVaccinationDate: "",
    
    // Дополнительные условия
    specialFeatures: "",
    deliveryTerms: "",
    additionalAgreements: "",
    recommendedFood: "",
    
    // Дата и место договора
    contractDate: new Date().toISOString().split('T')[0],
    contractPlace: "г. Каяани, Финляндия",
  });

  useEffect(() => {
    loadData();
  }, []);

  useEffect(() => {
    fetch(`/version.txt?ts=${Date.now()}`)
      .then((res) => res.text())
      .then((text) => setBuildVersion(text.trim()))
      .catch(() => setBuildVersion(""));
  }, []);

  const loadData = async () => {
    try {
      const response = await fetch("/api/api.php?action=getContracts", {
        headers: { Authorization: `Bearer ${token}` },
      });
      const data = await response.json();
      if (data.success) {
        setTemplates(data.templates || []);
        setContracts(data.contracts || []);
        setPdfTemplate(data.pdfTemplate || "");
      }
    } catch (error) {
      console.error(error);
      toast.error("Ошибка загрузки данных");
    } finally {
      setLoading(false);
    }
  };

  const uploadPdfTemplate = async (file: File) => {
    const formData = new FormData();
    formData.append("pdf", file); // API ожидает поле "pdf"

    try {
      const response = await fetch("/api/api.php?action=uploadPdfTemplate", {
        method: "POST",
        headers: { Authorization: `Bearer ${token}` },
        body: formData,
      });

      const data = await response.json();
      console.log('Upload PDF response:', data); // Отладка
      
      if (data.success) {
        console.log('Setting pdfTemplate to:', data.url); // Отладка
        setPdfTemplate(data.url);
        toast.success("PDF шаблон загружен");
      } else {
        console.error('Upload failed:', data.message); // Отладка
        toast.error(data.message || "Ошибка загрузки");
      }
    } catch (error) {
      console.error('Upload error:', error); // Отладка
      toast.error("Ошибка сети");
    }
  };

  const handleChange = (field: keyof ContractData, value: string | boolean) => {
    setFormData(prev => ({ ...prev, [field]: value }));
  };

  const saveAsTemplate = async () => {
    const templateName = prompt("Введите название шаблона:");
    if (!templateName) return;

    setSaving(true);
    try {
      const response = await fetch("/api/api.php?action=saveContractTemplate", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          name: templateName,
          data: formData,
        }),
      });

      const data = await response.json();
      if (data.success) {
        toast.success("Шаблон сохранен");
        loadData();
      } else {
        toast.error(data.message || "Ошибка сохранения");
      }
    } catch (error) {
      toast.error("Ошибка сети");
    } finally {
      setSaving(false);
    }
  };

  const loadTemplate = (template: ContractTemplate) => {
    setFormData(template.data);
    setActiveTab("new");
    toast.success(`Шаблон "${template.name}" загружен`);
  };

  const deleteTemplate = async (id: number) => {
    if (!confirm("Удалить шаблон?")) return;

    try {
      const response = await fetch("/api/api.php?action=deleteContractTemplate", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ id }),
      });

      const data = await response.json();
      if (data.success) {
        toast.success("Шаблон удален");
        loadData();
      }
    } catch (error) {
      toast.error("Ошибка удаления");
    }
  };

  const buildFieldMap = () => ({
    '`contractNumber`': `DOG-${new Date().getFullYear()}-${String(Math.floor(Math.random() * 9999) + 1).padStart(4, '0')}`,
    '`contractDate`': formData.contractDate || new Date().toLocaleDateString('ru-RU'),
    '`contractPlace`': formData.contractPlace || '',

    '`kennelOwner`': formData.kennelOwner || '',
    '`kennelAddress`': formData.kennelAddress || '',
    '`kennelPhone`': formData.kennelPhone || '',
    '`kennelEmail`': formData.kennelEmail || '',
    '`kennelPassportSeries`': formData.kennelPassportSeries || '',
    '`kennelPassportNumber`': formData.kennelPassportNumber || '',
    '`kennelPassportIssuedBy`': formData.kennelPassportIssuedBy || '',
    '`kennelPassportIssuedDate`': formData.kennelPassportIssuedDate || '',

    '`buyerName`': formData.buyerName || '',
    '`buyerAddress`': formData.buyerAddress || '',
    '`buyerPhone`': formData.buyerPhone || '',
    '`buyerEmail`': formData.buyerEmail || '',
    '`buyerPassportSeries`': formData.buyerPassportSeries || '',
    '`buyerPassportNumber`': formData.buyerPassportNumber || '',
    '`buyerPassportIssuedBy`': formData.buyerPassportIssuedBy || '',
    '`buyerPassportIssuedDate`': formData.buyerPassportIssuedDate || '',

    '`dogFatherName`': formData.dogFatherName || '',
    '`dogFatherRegNumber`': formData.dogFatherRegNumber || '',
    '`dogMotherName`': formData.dogMotherName || '',
    '`dogMotherRegNumber`': formData.dogMotherRegNumber || '',

    '`dogName`': formData.dogName || '',
    '`dogBirthDate`': formData.dogBirthDate || '',
    '`dogColor`': formData.dogColor || '',
    '`dogChipNumber`': formData.dogChipNumber || '',
    '`dogPuppyCard`': formData.dogPuppyCard || '',

    '`purposeBreeding`': formData.purposeBreeding || false,
    '`purposeCompanion`': formData.purposeCompanion || false,
    '`purposeGeneral`': formData.purposeGeneral || false,

    '`price`': formData.price || '',
    '`depositAmount`': formData.depositAmount || '',
    '`depositDate`': formData.depositDate || '',
    '`remainingAmount`': formData.remainingAmount || '',
    '`finalPaymentDate`': formData.finalPaymentDate || '',

    '`dewormingDate`': formData.dewormingDate || '',
    '`vaccinationDates`': formData.vaccinationDates || '',
    '`vaccineName`': formData.vaccineName || '',
    '`nextDewormingDate`': formData.nextDewormingDate || '',
    '`nextVaccinationDate`': formData.nextVaccinationDate || '',

    '`specialFeatures`': formData.specialFeatures || '',
    '`deliveryTerms`': formData.deliveryTerms || '',
    '`additionalAgreements`': formData.additionalAgreements || '',
    '`recommendedFood`': formData.recommendedFood || ''
  });

  const bytesToBase64 = (bytes: Uint8Array) => {
    let binary = '';
    const chunkSize = 0x8000;
    for (let i = 0; i < bytes.length; i += chunkSize) {
      binary += String.fromCharCode(...bytes.subarray(i, i + chunkSize));
    }
    return btoa(binary);
  };

  const buildFilledPdfBytes = async () => {
    if (!pdfTemplate) return null;

    const pdfBytes = await fetch(pdfTemplate).then(res => res.arrayBuffer());
    const pdfDoc = await PDFDocument.load(pdfBytes);
    const form = pdfDoc.getForm();
    const fields = form.getFields();
    console.log('=== PDF FIELDS DEBUG ===');
    console.log('Total fields found:', fields.length);
    console.log('Field names:', fields.map(f => f.getName()));

    if (fields.length === 0) {
      return { bytes: null, filledCount: 0, notFoundCount: 0, hasFields: false, fieldNames: [] };
    }

    const fieldMap = buildFieldMap();
    let filledCount = 0;
    let notFoundCount = 0;
    const existingFieldNames = fields.map(f => f.getName());

    toast.info(`🔍 PDF: ${fields.length} полей найдено, заполняем ${Object.keys(fieldMap).length}`);

    Object.entries(fieldMap).forEach(([fieldName, value]) => {
      try {
        if (typeof value === 'boolean') {
          const checkbox = form.getCheckBox(fieldName);
          if (value) checkbox.check();
          else checkbox.uncheck();
          filledCount++;
        } else {
          const textField = form.getTextField(fieldName);
          textField.setText(String(value));
          filledCount++;
        }
      } catch (e) {
        notFoundCount++;
      }
    });

    toast.success(`✅ Заполнено: ${filledCount}, Не найдено: ${notFoundCount}`);

    // Сохраняем БЕЗ обновления внешнего вида (избегаем ошибок с кириллицей)
    const filledPdfBytes = await pdfDoc.save({ updateFieldAppearances: false });
    return { bytes: new Uint8Array(filledPdfBytes), filledCount, notFoundCount, hasFields: true, fieldNames: fields.map(f => f.getName()) };
  };

  const checkPdfFields = async () => {
    if (!pdfTemplate) {
      toast.error("Загрузите PDF шаблон договора");
      return;
    }

    try {
      const pdfBytes = await fetch(pdfTemplate).then(res => res.arrayBuffer());
      const pdfDoc = await PDFDocument.load(pdfBytes);
      const form = pdfDoc.getForm();
      const fields = form.getFields();
      const names = fields.map(f => f.getName());

      setPdfFieldInfo({
        count: fields.length,
        names,
        lastChecked: new Date().toLocaleTimeString(),
        error: undefined
      });

      if (fields.length === 0) {
        toast.error("В PDF нет AcroForm полей. Скорее всего это XFA/плоский PDF.");
      } else {
        toast.success(`Найдено полей: ${fields.length}`);
      }
    } catch (error) {
      const message = (error as Error).message || "Ошибка проверки PDF";
      setPdfFieldInfo({ count: 0, names: [], lastChecked: new Date().toLocaleTimeString(), error: message });
      toast.error("Ошибка проверки PDF: " + message);
    }
  };

  const sendContract = async () => {
    document.title = "🔴 START sendContract";
    
    // Валидация
    if (!formData.buyerName || !formData.buyerEmail || !formData.dogName || !formData.price) {
      toast.error("Заполните все обязательные поля");
      return;
    }
    if (!pdfTemplate) {
      toast.error("Загрузите PDF шаблон");
      return;
    }

    setSending(true);
    document.title = "⏱️ Loading PDF...";
    
    try {
      // 1. Загружаем PDF
      const pdfBytes = await fetch(pdfTemplate).then(res => res.arrayBuffer());
      document.title = "⏱️ Parsing PDF...";
      const pdfDoc = await PDFDocument.load(pdfBytes);
      const form = pdfDoc.getForm();
      const fields = form.getFields();
      
      toast.info(`PDF: ${fields.length} полей найдено`);
      document.title = `⏱️ Found ${fields.length} fields`;
      
      // 2. Заполняем поля (используем ту же логику что в test_pdf_fill.html)
      const fieldMap = buildFieldMap();
      let filled = 0;
      
      for (const [fieldName, value] of Object.entries(fieldMap)) {
        try {
          if (typeof value === 'boolean') {
            const checkbox = form.getCheckBox(fieldName);
            value ? checkbox.check() : checkbox.uncheck();
          } else {
            const textField = form.getTextField(fieldName);
            textField.setText(String(value));
          }
          filled++;
        } catch (e) {
          // Поле не найдено - пропускаем
        }
      }
      
      document.title = `⏱️ Filled ${filled} fields`;
      toast.success(`✅ Заполнено: ${filled} полей`);
      
      // 3. Сохраняем PDF (БЕЗ updateFieldAppearances)
      document.title = "⏱️ Saving PDF...";
      const filledPdfBytes = await pdfDoc.save({ updateFieldAppearances: false });
      toast.info(`PDF saved: ${filledPdfBytes.length} bytes`);
      
      // 4. Upload PDF (как в test_pdf_fill.html)
      document.title = "⏱️ Uploading PDF...";
      const blob = new Blob([filledPdfBytes], { type: 'application/pdf' });
      const formData2 = new FormData();
      formData2.append('file', blob, 'contract.pdf');
      
      const uploadRes = await fetch('/api/api.php?action=uploadcontract', {
        method: 'POST',
        body: formData2
      });
      const uploadData = await uploadRes.json();
      
      if (!uploadData.success) {
        throw new Error('Upload failed: ' + uploadData.message);
      }
      
      document.title = "⏱️ Sending email...";
      toast.info(`Uploaded to: ${uploadData.path}`);
      
      // 5. Отправляем email с загруженным PDF
      const emailRes = await fetch('/api/api.php?action=sendContractPdf', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
        body: JSON.stringify({
          data: formData,
          pdfTemplate: uploadData.path,
          useUploadedPdf: true
        })
      });
      
      const emailData = await emailRes.json();
      document.title = "✅ DONE!";
      
      if (emailData.success) {
        toast.success(`Договор отправлен на ${formData.buyerEmail}!`);
        loadData();
        // Очистка формы...
        setFormData({
          ...formData,
          buyerName: "", buyerAddress: "", buyerPhone: "", buyerEmail: "",
          buyerPassportSeries: "", buyerPassportNumber: "", buyerPassportIssuedBy: "", buyerPassportIssuedDate: "",
          dogFatherName: "", dogFatherRegNumber: "", dogMotherName: "", dogMotherRegNumber: "",
          dogName: "", dogBirthDate: "", dogGender: "", dogColor: "", dogChipNumber: "", dogPuppyCard: "",
          purposeBreeding: false, purposeCompanion: false, purposeGeneral: false,
          price: "", depositAmount: "", depositDate: "", remainingAmount: "", finalPaymentDate: "",
          dewormingDate: "", vaccinationDates: "", vaccineName: "", nextDewormingDate: "", nextVaccinationDate: "",
          specialFeatures: "", deliveryTerms: "", additionalAgreements: "", recommendedFood: ""
        });
      } else {
        toast.error("Ошибка: " + emailData.message);
      }
      
    } catch (error) {
      document.title = "❌ ERROR";
      toast.error("Ошибка: " + (error as Error).message);
      console.error(error);
    } finally {
      setSending(false);
      document.title = "Админ Панель - MATRANG";
    }
  };
        
        if (filledResult?.bytes) {
          toast.info(`⏱️ bytesToBase64 START, bytes: ${filledResult.bytes.length}`, { duration: 2000 });
          filledPdfBase64 = bytesToBase64(filledResult.bytes);
          toast.success(`⏱️ bytesToBase64 DONE at ${(performance.now()-t0).toFixed(0)}ms\nBase64 length: ${filledPdfBase64.length}`, { duration: 3000 });
          toast.success(`✅ PDF заполнен: ${filledResult.filledCount} полей`);
        } else {
          toast.error("❌ buildFilledPdfBytes вернул null!");
        }
      } catch (e) {
        toast.error(`❌ EXCEPTION: ${(e as Error).message}`, { duration: 5000 });
      }

      toast.info(`⏱️ Before fetch at ${(performance.now()-t0).toFixed(0)}ms`, { duration: 2000 });
      const response = await fetch("/api/api.php?action=sendContractPdf", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          data: formData,
          pdfTemplate: pdfTemplate,
          filledPdfBase64: filledPdfBase64,
        }),
      });

      toast.success(`⏱️ After fetch at ${(performance.now()-t0).toFixed(0)}ms`, { duration: 2000 });
      const data = await response.json();
      toast.success(`⏱️ After json() at ${(performance.now()-t0).toFixed(0)}ms\nSuccess: ${data.success}`, { duration: 3000 });
      
      if (data.success) {
        const message = data.emailSent 
          ? `Договор №${data.contract.contractNumber} отправлен на email ${formData.buyerEmail}` 
          : "Договор создан (email не отправлен)";
        toast.success(message);
        toast.success(`⏱️ TOTAL TIME: ${(performance.now()-t0).toFixed(0)}ms`, { duration: 5000 });
        loadData();
        // Очистка формы - оставляем данные питомника, очищаем данные покупателя и щенка
        setFormData({
          ...formData,
          // Очищаем данные покупателя
          buyerName: "",
          buyerAddress: "",
          buyerPhone: "",
          buyerEmail: "",
          buyerPassportSeries: "",
          buyerPassportNumber: "",
          buyerPassportIssuedBy: "",
          buyerPassportIssuedDate: "",
          // Очищаем данные щенка (кроме породы)
          dogFatherName: "",
          dogFatherRegNumber: "",
          dogMotherName: "",
          dogMotherRegNumber: "",
          dogName: "",
          dogBirthDate: "",
          dogGender: "",
          dogColor: "",
          dogChipNumber: "",
          dogPuppyCard: "",
          // Очищаем цели
          purposeBreeding: false,
          purposeCompanion: false,
          purposeGeneral: false,
          // Очищаем финансы
          price: "",
          depositAmount: "",
          depositDate: "",
          remainingAmount: "",
          finalPaymentDate: "",
          // Очищаем вакцинацию
          dewormingDate: "",
          vaccinationDates: "",
          vaccineName: "",
          nextDewormingDate: "",
          nextVaccinationDate: "",
          // Очищаем доп.поля
          specialFeatures: "",
          deliveryTerms: "",
          additionalAgreements: "",
          // Обновляем дату
          contractDate: new Date().toISOString().split('T')[0],
        });
      } else {
        toast.error(data.message || "Ошибка отправки");
      }
    } catch (error) {
      toast.error("Ошибка сети");
    } finally {
      setSending(false);
    }
  };

  const generatePreview = async () => {
    if (!pdfTemplate) {
      toast.error("Загрузите PDF шаблон договора");
      return;
    }

    try {
      toast.info("Генерация PDF...");
      
      const filledResult = await buildFilledPdfBytes();
      if (!filledResult || !filledResult.hasFields) {
        toast.error("В PDF нет AcroForm полей! Создайте именно форму (Acrobat/Foxit) и задайте имена полей.");
        window.open(pdfTemplate, '_blank');
        return;
      }

      console.log(`Filled ${filledResult.filledCount} fields, ${filledResult.notFoundCount} fields not found in PDF`);

      if (filledResult.filledCount === 0) {
        toast.warning(`Ни одно поле не заполнено! Проверьте названия полей в PDF.`);
      } else {
        toast.success(`Заполнено полей: ${filledResult.filledCount}`);
      }
      
      const blob = new Blob([filledResult.bytes!], { type: 'application/pdf' });
      const url = URL.createObjectURL(blob);
      window.open(url, '_blank');
      
    } catch (error) {
      console.error('PDF generation error:', error);
      toast.error("Ошибка: " + (error as Error).message);
      
      // Открываем оригинальный PDF для просмотра
      window.open(pdfTemplate, '_blank');
    }
  };

  if (loading) {
    return <div className="p-8 text-center">Загрузка...</div>;
  }

  return (
    <div className="min-h-screen bg-background p-8">
      <div className="max-w-6xl mx-auto">
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-3xl font-bold">Управление договорами</h1>
          {buildVersion && (
            <span className="text-xs text-muted-foreground">build: {buildVersion}</span>
          )}
        </div>

        <Tabs value={activeTab} onValueChange={setActiveTab}>
          <TabsList>
            <TabsTrigger value="new">Новый договор</TabsTrigger>
            <TabsTrigger value="templates">Шаблоны ({templates.length})</TabsTrigger>
            <TabsTrigger value="archive">Архив ({contracts.length})</TabsTrigger>
          </TabsList>

          <TabsContent value="new" className="space-y-6 mt-6">
            <div className="bg-card border border-border rounded-lg p-6">
              <h2 className="text-xl font-semibold mb-4">PDF Шаблон договора</h2>
              {pdfTemplate ? (
                <div className="flex items-center gap-4">
                  <div className="flex-1">
                    <p className="text-sm text-muted-foreground">Шаблон загружен</p>
                    <a href={pdfTemplate} target="_blank" rel="noopener noreferrer" className="text-primary hover:underline">
                      Просмотреть PDF
                    </a>
                    <div className="mt-2 text-xs text-muted-foreground">
                      Поля формы: {pdfFieldInfo.count} {pdfFieldInfo.lastChecked ? `• проверено ${pdfFieldInfo.lastChecked}` : ''}
                      {pdfFieldInfo.error ? ` • ошибка: ${pdfFieldInfo.error}` : ''}
                    </div>
                    {pdfFieldInfo.names.length > 0 && (
                      <div className="mt-1 max-h-24 overflow-auto text-xs">
                        {pdfFieldInfo.names.map((name) => (
                          <div key={name}>{name}</div>
                        ))}
                      </div>
                    )}
                  </div>
                  <label className="cursor-pointer">
                    <Button variant="outline" size="sm" asChild>
                      <span>
                        <Upload className="w-4 h-4 mr-2" />
                        Заменить шаблон
                      </span>
                    </Button>
                    <input
                      type="file"
                      accept=".pdf"
                      onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (file) uploadPdfTemplate(file);
                      }}
                      className="hidden"
                    />
                  </label>
                  <Button variant="secondary" size="sm" onClick={checkPdfFields}>
                    Проверить поля
                  </Button>
                </div>
              ) : (
                <label className="flex items-center justify-center gap-2 p-8 border-2 border-dashed border-border rounded cursor-pointer hover:bg-muted transition-colors">
                  <Upload className="w-6 h-6" />
                  <span>Загрузите PDF шаблон договора</span>
                  <input
                    type="file"
                    accept=".pdf"
                    onChange={(e) => {
                      const file = e.target.files?.[0];
                      if (file) uploadPdfTemplate(file);
                    }}
                    className="hidden"
                  />
                </label>
              )}
              <p className="text-xs text-muted-foreground mt-2">
                💡 Загрузите PDF договора с заполняемыми полями (созданный в Adobe Acrobat)
              </p>
            </div>

            {/* ЭКСТРЕННАЯ КНОПКА ОТПРАВКИ - ВСЕГДА ВИДНА */}
            <div className="bg-red-50 border-2 border-red-500 rounded-lg p-6">
              <h2 className="text-xl font-bold text-red-600 mb-4">🚀 НОВАЯ КНОПКА ОТПРАВКИ (ТЕСТ)</h2>
              <button
                id="emergency-send-btn"
                className="w-full inline-flex items-center justify-center gap-2 rounded-md bg-red-600 px-8 py-4 text-lg font-bold text-white hover:bg-red-700 disabled:opacity-50"
                disabled={sending}
                onClick={async (e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  alert("🔴 EMERGENCY BUTTON CLICKED!");
                  
                  if (!formData.buyerEmail || !pdfTemplate) {
                    alert("Нет email или PDF шаблона!");
                    return;
                  }
                  
                  setSending(true);
                  try {
                    const pdfBytes = await fetch(pdfTemplate).then(res => res.arrayBuffer());
                    const pdfDoc = await PDFDocument.load(pdfBytes);
                    const form = pdfDoc.getForm();
                    const fieldMap = buildFieldMap();
                    let filled = 0;
                    for (const [name, val] of Object.entries(fieldMap)) {
                      try {
                        if (typeof val === 'boolean') {
                          const cb = form.getCheckBox(name);
                          val ? cb.check() : cb.uncheck();
                        } else {
                          form.getTextField(name).setText(String(val));
                        }
                        filled++;
                      } catch {}
                    }
                    const saved = await pdfDoc.save({ updateFieldAppearances: false });
                    const blob = new Blob([saved], { type: 'application/pdf' });
                    const fd = new FormData();
                    fd.append('file', blob, 'contract.pdf');
                    const upRes = await fetch('/api/api.php?action=uploadcontract', { method: 'POST', body: fd });
                    const upData = await upRes.json();
                    const emailRes = await fetch('/api/api.php?action=sendContractPdf', {
                      method: 'POST',
                      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
                      body: JSON.stringify({ data: formData, pdfTemplate: upData.path, useUploadedPdf: true })
                    });
                    const emailData = await emailRes.json();
                    alert(emailData.success ? `✅ ОТПРАВЛЕНО! Заполнено ${filled} полей` : "❌ " + emailData.message);
                    if (emailData.success) loadData();
                  } catch (err) {
                    alert("❌ ОШИБКА: " + (err as Error).message);
                  } finally {
                    setSending(false);
                  }
                }}
              >
                {sending ? "⏳ ОТПРАВКА..." : "🚀 ОТПРАВИТЬ ДОГОВОР (EMERGENCY)"}
              </button>
              <p className="text-sm text-red-600 mt-2">Эта кнопка использует тот же код что и test_pdf_fill.html</p>
            </div>

            <div className="bg-card border border-border rounded-lg p-6">
              <h2 className="text-xl font-semibold mb-4">Данные питомника / Заводчика</h2>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium mb-1">Название питомника</label>
                  <Input
                    value={formData.kennelName}
                    onChange={(e) => handleChange('kennelName', e.target.value)}
                    placeholder="GREAT LEGACY BULLY"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Владелец питомника / ФИО Заводчика *</label>
                  <Input
                    value={formData.kennelOwner}
                    onChange={(e) => handleChange('kennelOwner', e.target.value)}
                    placeholder="Иванов Иван Иванович"
                  />
                </div>
                <div className="col-span-2">
                  <label className="block text-sm font-medium mb-1">Адрес</label>
                  <Input
                    value={formData.kennelAddress}
                    onChange={(e) => handleChange('kennelAddress', e.target.value)}
                    placeholder="г. Каяани, Финляндия"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Телефон</label>
                  <Input
                    value={formData.kennelPhone}
                    onChange={(e) => handleChange('kennelPhone', e.target.value)}
                    placeholder="+7 (900) 455-27-16"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Email</label>
                  <Input
                    value={formData.kennelEmail}
                    onChange={(e) => handleChange('kennelEmail', e.target.value)}
                    placeholder="greatlegacybully@gmail.com"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Паспорт серия</label>
                  <Input
                    value={formData.kennelPassportSeries}
                    onChange={(e) => handleChange('kennelPassportSeries', e.target.value)}
                    placeholder="1234"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Паспорт номер</label>
                  <Input
                    value={formData.kennelPassportNumber}
                    onChange={(e) => handleChange('kennelPassportNumber', e.target.value)}
                    placeholder="567890"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Паспорт выдан</label>
                  <Input
                    value={formData.kennelPassportIssuedBy}
                    onChange={(e) => handleChange('kennelPassportIssuedBy', e.target.value)}
                    placeholder="УФМС..."
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Дата выдачи паспорта</label>
                  <Input
                    type="date"
                    value={formData.kennelPassportIssuedDate}
                    onChange={(e) => handleChange('kennelPassportIssuedDate', e.target.value)}
                  />
                </div>
              </div>
            </div>

            <div className="bg-card border border-border rounded-lg p-6">
              <h2 className="text-xl font-semibold mb-4">Данные покупателя / Владельца</h2>
              <div className="grid grid-cols-2 gap-4">
                <div className="col-span-2">
                  <label className="block text-sm font-medium mb-1">ФИО покупателя *</label>
                  <Input
                    value={formData.buyerName}
                    onChange={(e) => handleChange('buyerName', e.target.value)}
                    placeholder="Петров Петр Петрович"
                  />
                </div>
                <div className="col-span-2">
                  <label className="block text-sm font-medium mb-1">Адрес регистрации</label>
                  <Input
                    value={formData.buyerAddress}
                    onChange={(e) => handleChange('buyerAddress', e.target.value)}
                    placeholder="г. Москва, ул. ..."
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Телефон</label>
                  <Input
                    value={formData.buyerPhone}
                    onChange={(e) => handleChange('buyerPhone', e.target.value)}
                    placeholder="+7 (___) ___-__-__"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Email покупателя *</label>
                  <Input
                    value={formData.buyerEmail}
                    onChange={(e) => handleChange('buyerEmail', e.target.value)}
                    placeholder="buyer@email.com"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Паспорт серия</label>
                  <Input
                    value={formData.buyerPassportSeries}
                    onChange={(e) => handleChange('buyerPassportSeries', e.target.value)}
                    placeholder="1234"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Паспорт номер</label>
                  <Input
                    value={formData.buyerPassportNumber}
                    onChange={(e) => handleChange('buyerPassportNumber', e.target.value)}
                    placeholder="567890"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Паспорт выдан</label>
                  <Input
                    value={formData.buyerPassportIssuedBy}
                    onChange={(e) => handleChange('buyerPassportIssuedBy', e.target.value)}
                    placeholder="УФМС..."
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Дата выдачи паспорта</label>
                  <Input
                    type="date"
                    value={formData.buyerPassportIssuedDate}
                    onChange={(e) => handleChange('buyerPassportIssuedDate', e.target.value)}
                  />
                </div>
              </div>
            </div>

            <div className="bg-card border border-border rounded-lg p-6">
              <h2 className="text-xl font-semibold mb-4">Данные о щенке</h2>
              
              {/* Родители */}
              <div className="mb-4 pb-4 border-b border-border">
                <h3 className="text-sm font-semibold mb-3 text-muted-foreground">Родители щенка</h3>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium mb-1">Кличка отца</label>
                    <Input
                      value={formData.dogFatherName}
                      onChange={(e) => handleChange('dogFatherName', e.target.value)}
                      placeholder="CHAMPION NAME"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium mb-1">Рег. номер отца</label>
                    <Input
                      value={formData.dogFatherRegNumber}
                      onChange={(e) => handleChange('dogFatherRegNumber', e.target.value)}
                      placeholder="ABKC/UKC..."
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium mb-1">Кличка матери</label>
                    <Input
                      value={formData.dogMotherName}
                      onChange={(e) => handleChange('dogMotherName', e.target.value)}
                      placeholder="CHAMPION NAME"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium mb-1">Рег. номер матери</label>
                    <Input
                      value={formData.dogMotherRegNumber}
                      onChange={(e) => handleChange('dogMotherRegNumber', e.target.value)}
                      placeholder="ABKC/UKC..."
                    />
                  </div>
                </div>
              </div>

              {/* Данные щенка */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium mb-1">Кличка щенка *</label>
                  <Input
                    value={formData.dogName}
                    onChange={(e) => handleChange('dogName', e.target.value)}
                    placeholder="MATRANG"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Порода</label>
                  <Input
                    value={formData.dogBreed}
                    onChange={(e) => handleChange('dogBreed', e.target.value)}
                    placeholder="Американский булли"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Дата рождения</label>
                  <Input
                    type="date"
                    value={formData.dogBirthDate}
                    onChange={(e) => handleChange('dogBirthDate', e.target.value)}
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Пол</label>
                  <select
                    value={formData.dogGender}
                    onChange={(e) => handleChange('dogGender', e.target.value)}
                    className="w-full bg-background border border-border px-3 py-2 rounded"
                  >
                    <option value="">Выберите...</option>
                    <option value="Кобель">Кобель</option>
                    <option value="Сука">Сука</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Окрас</label>
                  <Input
                    value={formData.dogColor}
                    onChange={(e) => handleChange('dogColor', e.target.value)}
                    placeholder="Blue Fawn"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Номер чипа</label>
                  <Input
                    value={formData.dogChipNumber}
                    onChange={(e) => handleChange('dogChipNumber', e.target.value)}
                    placeholder="123456789012345"
                  />
                </div>
                <div className="col-span-2">
                  <label className="block text-sm font-medium mb-1">Щенячья карточка ABKC</label>
                  <Input
                    value={formData.dogPuppyCard}
                    onChange={(e) => handleChange('dogPuppyCard', e.target.value)}
                    placeholder="ABKC номер"
                  />
                </div>
              </div>

              {/* Цель приобретения */}
              <div className="mt-4 pt-4 border-t border-border">
                <h3 className="text-sm font-semibold mb-3">Цель приобретения</h3>
                <div className="space-y-2">
                  <label className="flex items-center gap-2">
                    <input
                      type="checkbox"
                      checked={formData.purposeBreeding}
                      onChange={(e) => handleChange('purposeBreeding', e.target.checked)}
                      className="w-4 h-4"
                    />
                    <span className="text-sm">Для племенной работы (разведение)</span>
                  </label>
                  <label className="flex items-center gap-2">
                    <input
                      type="checkbox"
                      checked={formData.purposeCompanion}
                      onChange={(e) => handleChange('purposeCompanion', e.target.checked)}
                      className="w-4 h-4"
                    />
                    <span className="text-sm">Компаньон (без разведения)</span>
                  </label>
                  <label className="flex items-center gap-2">
                    <input
                      type="checkbox"
                      checked={formData.purposeGeneral}
                      onChange={(e) => handleChange('purposeGeneral', e.target.checked)}
                      className="w-4 h-4"
                    />
                    <span className="text-sm">Общение, не исключающее разведения</span>
                  </label>
                </div>
              </div>
            </div>

            <div className="bg-card border border-border rounded-lg p-6">
              <h2 className="text-xl font-semibold mb-4">Финансовые условия</h2>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium mb-1">Полная стоимость (руб.) *</label>
                  <Input
                    type="number"
                    value={formData.price}
                    onChange={(e) => handleChange('price', e.target.value)}
                    placeholder="150000"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Сумма задатка (руб.)</label>
                  <Input
                    type="number"
                    value={formData.depositAmount}
                    onChange={(e) => handleChange('depositAmount', e.target.value)}
                    placeholder="30000"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Дата внесения задатка</label>
                  <Input
                    type="date"
                    value={formData.depositDate}
                    onChange={(e) => handleChange('depositDate', e.target.value)}
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Остаток к оплате (руб.)</label>
                  <Input
                    type="number"
                    value={formData.remainingAmount}
                    onChange={(e) => handleChange('remainingAmount', e.target.value)}
                    placeholder={formData.price && formData.depositAmount ? 
                      String(Number(formData.price) - Number(formData.depositAmount)) : "120000"}
                  />
                </div>
                <div className="col-span-2">
                  <label className="block text-sm font-medium mb-1">Срок окончательного расчета</label>
                  <Input
                    type="date"
                    value={formData.finalPaymentDate}
                    onChange={(e) => handleChange('finalPaymentDate', e.target.value)}
                  />
                </div>
              </div>
            </div>

            <div className="bg-card border border-border rounded-lg p-6">
              <h2 className="text-xl font-semibold mb-4">Вакцинация и ветеринарные процедуры</h2>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium mb-1">Дата выгонки глистов</label>
                  <Input
                    type="date"
                    value={formData.dewormingDate}
                    onChange={(e) => handleChange('dewormingDate', e.target.value)}
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Даты прививок</label>
                  <Input
                    value={formData.vaccinationDates}
                    onChange={(e) => handleChange('vaccinationDates', e.target.value)}
                    placeholder="01.01.2025, 15.01.2025"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Название вакцины</label>
                  <Input
                    value={formData.vaccineName}
                    onChange={(e) => handleChange('vaccineName', e.target.value)}
                    placeholder="Nobivac, Eurican..."
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Следующая обработка от глистов</label>
                  <Input
                    type="date"
                    value={formData.nextDewormingDate}
                    onChange={(e) => handleChange('nextDewormingDate', e.target.value)}
                  />
                </div>
                <div className="col-span-2">
                  <label className="block text-sm font-medium mb-1">Следующая вакцинация</label>
                  <Input
                    type="date"
                    value={formData.nextVaccinationDate}
                    onChange={(e) => handleChange('nextVaccinationDate', e.target.value)}
                  />
                </div>
              </div>
            </div>

            <div className="bg-card border border-border rounded-lg p-6">
              <h2 className="text-xl font-semibold mb-4">Дополнительная информация</h2>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium mb-1">Индивидуальные особенности щенка</label>
                  <Textarea
                    value={formData.specialFeatures}
                    onChange={(e) => handleChange('specialFeatures', e.target.value)}
                    placeholder="Особенности экстерьера, характера, нюансы здоровья..."
                    className="min-h-24"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Условия доставки</label>
                  <Textarea
                    value={formData.deliveryTerms}
                    onChange={(e) => handleChange('deliveryTerms', e.target.value)}
                    placeholder="Способ доставки, стоимость, сроки..."
                    className="min-h-20"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Дополнительные соглашения</label>
                  <Textarea
                    value={formData.additionalAgreements}
                    onChange={(e) => handleChange('additionalAgreements', e.target.value)}
                    placeholder="Дополнительные условия договора, гарантии, особые требования..."
                    className="min-h-24"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Рекомендуемый корм</label>
                  <Input
                    value={formData.recommendedFood}
                    onChange={(e) => handleChange('recommendedFood', e.target.value)}
                    placeholder="Royal Canin, Acana..."
                  />
                </div>
              </div>
            </div>

            <div className="bg-card border border-border rounded-lg p-6">
              <h2 className="text-xl font-semibold mb-4">Дата и место договора</h2>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium mb-1">Дата договора</label>
                  <Input
                    type="date"
                    value={formData.contractDate}
                    onChange={(e) => handleChange('contractDate', e.target.value)}
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Место составления</label>
                  <Input
                    value={formData.contractPlace}
                    onChange={(e) => handleChange('contractPlace', e.target.value)}
                    placeholder="г. Каяани, Финляндия"
                  />
                </div>
              </div>
            </div>

            <div className="flex gap-4">
              <Button onClick={saveAsTemplate} disabled={saving} variant="outline">
                <Save className="w-4 h-4 mr-2" />
                Сохранить как шаблон
              </Button>
              <Button onClick={generatePreview} variant="outline">
                <FileText className="w-4 h-4 mr-2" />
                Предпросмотр
              </Button>
              
              {/* НОВАЯ КНОПКА - ТЕСТОВАЯ */}
              <button
                id="send-contract-btn-new"
                className="inline-flex items-center justify-center gap-2 rounded-md bg-red-600 px-6 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                disabled={sending}
                onClick={async (e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  alert("🔴 НОВАЯ КНОПКА РАБОТАЕТ!");
                  document.title = "🔴 NEW BUTTON CLICKED!";
                  
                  if (!formData.buyerName || !formData.buyerEmail || !pdfTemplate) {
                    alert("Заполните поля!");
                    return;
                  }
                  
                  setSending(true);
                  try {
                    // Загружаем PDF
                    const pdfBytes = await fetch(pdfTemplate).then(res => res.arrayBuffer());
                    const pdfDoc = await PDFDocument.load(pdfBytes);
                    const form = pdfDoc.getForm();
                    
                    // Заполняем
                    const fieldMap = buildFieldMap();
                    let filled = 0;
                    for (const [name, val] of Object.entries(fieldMap)) {
                      try {
                        if (typeof val === 'boolean') {
                          const cb = form.getCheckBox(name);
                          val ? cb.check() : cb.uncheck();
                        } else {
                          form.getTextField(name).setText(String(val));
                        }
                        filled++;
                      } catch {}
                    }
                    
                    alert(`Заполнено ${filled} полей!`);
                    
                    // Сохраняем
                    const saved = await pdfDoc.save({ updateFieldAppearances: false });
                    
                    // Upload
                    const blob = new Blob([saved], { type: 'application/pdf' });
                    const fd = new FormData();
                    fd.append('file', blob, 'contract.pdf');
                    
                    const upRes = await fetch('/api/api.php?action=uploadcontract', {
                      method: 'POST',
                      body: fd
                    });
                    const upData = await upRes.json();
                    
                    // Send email
                    const emailRes = await fetch('/api/api.php?action=sendContractPdf', {
                      method: 'POST',
                      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
                      body: JSON.stringify({
                        data: formData,
                        pdfTemplate: upData.path,
                        useUploadedPdf: true
                      })
                    });
                    
                    const emailData = await emailRes.json();
                    if (emailData.success) {
                      alert("✅ ОТПРАВЛЕНО НА EMAIL!");
                      loadData();
                    } else {
                      alert("Ошибка: " + emailData.message);
                    }
                  } catch (err) {
                    alert("Ошибка: " + (err as Error).message);
                  } finally {
                    setSending(false);
                    document.title = "Админ Панель - MATRANG";
                  }
                }}
              >
                {sending ? "⏳ Отправка..." : "🚀 ОТПРАВИТЬ НОВАЯ КНОПКА"}
              </button>
            </div>
          </TabsContent>

          <TabsContent value="templates" className="mt-6">
            <div className="space-y-4">
              {templates.length === 0 ? (
                <div className="text-center py-12 text-muted-foreground">
                  Нет сохраненных шаблонов
                </div>
              ) : (
                templates.map((template) => (
                  <div key={template.id} className="bg-card border border-border rounded-lg p-4 flex items-center justify-between">
                    <div>
                      <h3 className="font-semibold">{template.name}</h3>
                      <p className="text-sm text-muted-foreground">
                        Создан: {new Date(template.createdAt).toLocaleDateString('ru-RU')}
                      </p>
                    </div>
                    <div className="flex gap-2">
                      <Button variant="outline" size="sm" onClick={() => loadTemplate(template)}>
                        <Download className="w-4 h-4 mr-2" />
                        Загрузить
                      </Button>
                      <Button variant="destructive" size="sm" onClick={() => deleteTemplate(template.id)}>
                        <Trash2 className="w-4 h-4" />
                      </Button>
                    </div>
                  </div>
                ))
              )}
            </div>
          </TabsContent>

          <TabsContent value="archive" className="mt-6">
            <div className="space-y-4">
              {contracts.length === 0 ? (
                <div className="text-center py-12 text-muted-foreground">
                  Архив пуст
                </div>
              ) : (
                contracts.map((contract) => {
                  const getStatusBadge = () => {
                    if (contract.status === 'signed' || contract.signedAt) {
                      return <span className="inline-flex items-center px-2 py-1 rounded bg-green-100 text-green-700 text-xs font-medium">✓ Подписан</span>;
                    }
                    if (contract.status === 'sent' || contract.sentAt) {
                      return <span className="inline-flex items-center px-2 py-1 rounded bg-yellow-100 text-yellow-700 text-xs font-medium">📧 Отправлен на подпись</span>;
                    }
                    if (contract.status === 'sent_by_email') {
                      return <span className="inline-flex items-center px-2 py-1 rounded bg-blue-100 text-blue-700 text-xs font-medium">✉️ Отправлен Email</span>;
                    }
                    return <span className="inline-flex items-center px-2 py-1 rounded bg-gray-100 text-gray-700 text-xs font-medium">⊙ Черновик</span>;
                  };
                  
                  return (
                  <div key={contract.id} className="bg-card border border-border rounded-lg p-4">
                    <div className="flex items-start justify-between mb-3">
                      <div className="flex-1">
                        <div className="flex items-center gap-2 mb-1">
                          <h3 className="font-semibold">Договор №{contract.contractNumber}</h3>
                          {getStatusBadge()}
                        </div>
                        <p className="text-sm text-muted-foreground">
                          Покупатель: {contract.data.buyerName} ({contract.data.buyerEmail})
                        </p>
                        <p className="text-sm text-muted-foreground">
                          Щенок: {contract.data.dogName}
                        </p>
                        <p className="text-sm text-muted-foreground">
                          Цена: {contract.data.price} ₽
                        </p>
                        <div className="flex gap-4 mt-2 text-xs text-muted-foreground">
                          <span>Создан: {new Date(contract.createdAt).toLocaleDateString('ru-RU', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                          })}</span>
                          {contract.sentAt && (
                            <span>Отправлен: {new Date(contract.sentAt).toLocaleDateString('ru-RU', {
                              day: '2-digit',
                              month: '2-digit',
                              year: 'numeric',
                              hour: '2-digit',
                              minute: '2-digit'
                            })}</span>
                          )}
                          {contract.signedAt && (
                            <span className="text-green-600 font-medium">Подписан: {new Date(contract.signedAt).toLocaleDateString('ru-RU', {
                              day: '2-digit',
                              month: '2-digit',
                              year: 'numeric',
                              hour: '2-digit',
                              minute: '2-digit'
                            })}</span>
                          )}
                        </div>
                        {contract.adobeSignAgreementId && (
                          <p className="text-xs text-muted-foreground mt-1">
                            Adobe Sign ID: {contract.adobeSignAgreementId}
                          </p>
                        )}
                      </div>
                    </div>
                    <div className="flex gap-2">
                      {contract.signedDocumentUrl && (
                        <Button variant="outline" size="sm" asChild>
                          <a href={contract.signedDocumentUrl} download>
                            <Download className="w-4 h-4 mr-2" />
                            Скачать подписанный
                          </a>
                        </Button>
                      )}
                      <Button 
                        variant="ghost" 
                        size="sm"
                        onClick={() => {
                          // Показать детали договора
                          const details = Object.entries(contract.data)
                            .map(([key, value]) => `${key}: ${value}`)
                            .join('\n');
                          alert(`Детали договора №${contract.contractNumber}\n\n${details}`);
                        }}
                      >
                        <FileText className="w-4 h-4 mr-2" />
                        Детали
                      </Button>
                    </div>
                  </div>
                  );
                })
              )}
            </div>
          </TabsContent>
        </Tabs>
      </div>
    </div>
  );
};

export default ContractManager;
