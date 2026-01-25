import { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Save, Send, Download, FileText, Trash2, Plus, Archive, Upload } from "lucide-react";
import { toast } from "sonner";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

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
      if (data.success) {
        setPdfTemplate(data.url);
        toast.success("PDF шаблон загружен");
      } else {
        toast.error(data.message || "Ошибка загрузки");
      }
    } catch (error) {
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

  const sendContract = async () => {
    // Валидация обязательных полей
    if (!formData.buyerName || !formData.buyerEmail || !formData.dogName || !formData.price) {
      toast.error("Заполните все обязательные поля");
      return;
    }

    if (!pdfTemplate) {
      toast.error("Загрузите PDF шаблон договора");
      return;
    }

    setSending(true);
    try {
      const response = await fetch("/api/api.php?action=sendContractPdf", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          data: formData,
          pdfTemplate: pdfTemplate,
        }),
      });

      const data = await response.json();
      if (data.success) {
        toast.success("Договор отправлен на подпись через Adobe Sign");
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

  const generatePreview = () => {
    const contractHTML = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="UTF-8">
        <style>
          body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
          h1 { text-align: center; }
          .section { margin: 20px 0; }
          .field { margin: 10px 0; }
          .label { font-weight: bold; }
          .signature { margin-top: 50px; display: flex; justify-content: space-between; }
          .signature-block { text-align: center; }
        </style>
      </head>
      <body>
        <h1>GREAT LEGACY BULLY</h1>
        <h2>ДОГОВОР КУПЛИ-ПРОДАЖИ ЩЕНКА American Bully</h2>
        <p style="text-align: center;">№ ____ от ${formData.contractDate}</p>
        <p style="text-align: center;">${formData.contractPlace || ''}</p>
        
        <div class="section">
          <h3>1. ЗАВОДЧИК-ПРОДАВЕЦ</h3>
          <div class="field"><span class="label">ФИО:</span> ${formData.kennelOwner}</div>
          <div class="field"><span class="label">Адрес:</span> ${formData.kennelAddress}</div>
          <div class="field"><span class="label">Телефон:</span> ${formData.kennelPhone}</div>
          <div class="field"><span class="label">Email:</span> ${formData.kennelEmail}</div>
          ${formData.kennelPassportSeries ? `<div class="field"><span class="label">Паспорт:</span> ${formData.kennelPassportSeries} ${formData.kennelPassportNumber}</div>` : ''}
        </div>

        <div class="section">
          <h3>2. ПОКУПАТЕЛЬ-ВЛАДЕЛЕЦ</h3>
          <div class="field"><span class="label">ФИО:</span> ${formData.buyerName}</div>
          <div class="field"><span class="label">Адрес:</span> ${formData.buyerAddress}</div>
          <div class="field"><span class="label">Телефон:</span> ${formData.buyerPhone}</div>
          <div class="field"><span class="label">Email:</span> ${formData.buyerEmail}</div>
          ${formData.buyerPassportSeries ? `<div class="field"><span class="label">Паспорт:</span> ${formData.buyerPassportSeries} ${formData.buyerPassportNumber}</div>` : ''}
        </div>

        <div class="section">
          <h3>3. ПРЕДМЕТ ДОГОВОРА - ЩЕНОК</h3>
          ${formData.dogFatherName ? `
          <p><strong>Родители:</strong></p>
          <div class="field"><span class="label">Отец:</span> ${formData.dogFatherName} (${formData.dogFatherRegNumber || 'н/д'})</div>
          <div class="field"><span class="label">Мать:</span> ${formData.dogMotherName} (${formData.dogMotherRegNumber || 'н/д'})</div>
          ` : ''}
          <p><strong>Данные щенка:</strong></p>
          <div class="field"><span class="label">Кличка:</span> ${formData.dogName}</div>
          <div class="field"><span class="label">Порода:</span> ${formData.dogBreed}</div>
          <div class="field"><span class="label">Дата рождения:</span> ${formData.dogBirthDate}</div>
          <div class="field"><span class="label">Пол:</span> ${formData.dogGender}</div>
          <div class="field"><span class="label">Окрас:</span> ${formData.dogColor}</div>
          ${formData.dogChipNumber ? `<div class="field"><span class="label">№ чипа:</span> ${formData.dogChipNumber}</div>` : ''}
          ${formData.dogPuppyCard ? `<div class="field"><span class="label">Щенячья карточка:</span> ${formData.dogPuppyCard}</div>` : ''}
          ${(formData.purposeBreeding || formData.purposeCompanion || formData.purposeGeneral) ? `
          <p><strong>Цель приобретения:</strong></p>
          ${formData.purposeBreeding ? '<div class="field">☑ Для племенной работы (разведение)</div>' : ''}
          ${formData.purposeCompanion ? '<div class="field">☑ Компаньон (без разведения)</div>' : ''}
          ${formData.purposeGeneral ? '<div class="field">☑ Общение, не исключающее разведения</div>' : ''}
          ` : ''}
        </div>

        ${formData.vaccinationDates || formData.dewormingDate ? `
        <div class="section">
          <h3>4. ВАКЦИНАЦИЯ</h3>
          ${formData.dewormingDate ? `<div class="field"><span class="label">Выгонка глистов:</span> ${formData.dewormingDate}</div>` : ''}
          ${formData.vaccinationDates ? `<div class="field"><span class="label">Прививки:</span> ${formData.vaccinationDates}</div>` : ''}
          ${formData.vaccineName ? `<div class="field"><span class="label">Вакцина:</span> ${formData.vaccineName}</div>` : ''}
        </div>
        ` : ''}

        <div class="section">
          <h3>5. ФИНАНСОВЫЕ УСЛОВИЯ</h3>
          <div class="field"><span class="label">Полная стоимость:</span> ${formData.price} руб.</div>
          ${formData.depositAmount ? `<div class="field"><span class="label">Сумма задатка:</span> ${formData.depositAmount} руб. (внесен ${formData.depositDate || ''})</div>` : ''}
          ${formData.remainingAmount ? `<div class="field"><span class="label">Остаток к оплате:</span> ${formData.remainingAmount} руб.</div>` : ''}
          ${formData.finalPaymentDate ? `<div class="field"><span class="label">Срок оплаты:</span> не позднее ${formData.finalPaymentDate}</div>` : ''}
        </div>

        ${formData.additionalAgreements || formData.deliveryTerms || formData.specialFeatures ? `
        <div class="section">
          <h3>6. ДОПОЛНИТЕЛЬНЫЕ УСЛОВИЯ</h3>
          ${formData.specialFeatures ? `<p><strong>Особенности щенка:</strong><br>${formData.specialFeatures}</p>` : ''}
          ${formData.deliveryTerms ? `<p><strong>Условия доставки:</strong><br>${formData.deliveryTerms}</p>` : ''}
          ${formData.additionalAgreements ? `<p><strong>Доп. соглашения:</strong><br>${formData.additionalAgreements}</p>` : ''}
        </div>
        ` : ''}

        <div class="signature">
          <div class="signature-block">
            <p>ЗАВОДЧИК-ПРОДАВЕЦ</p>
            <p>_________________</p>
            <p>${formData.kennelOwner}</p>
            <p>Дата: _____________</p>
          </div>
          <div class="signature-block">
            <p>ПОКУПАТЕЛЬ</p>
            <p>_________________</p>
            <p>${formData.buyerName}</p>
            <p>Дата: _____________</p>
          </div>
        </div>
      </body>
      </html>
    `;

    const blob = new Blob([contractHTML], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    window.open(url, '_blank');
  };

  if (loading) {
    return <div className="p-8 text-center">Загрузка...</div>;
  }

  return (
    <div className="min-h-screen bg-background p-8">
      <div className="max-w-6xl mx-auto">
        <h1 className="text-3xl font-bold mb-6">Управление договорами</h1>

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
              <Button onClick={sendContract} disabled={sending}>
                <Send className="w-4 h-4 mr-2" />
                {sending ? "Отправка..." : "Отправить на email"}
              </Button>
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
