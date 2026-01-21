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
  // Данные питомника
  kennelName: string;
  kennelOwner: string;
  kennelAddress: string;
  kennelPhone: string;
  kennelEmail: string;
  kennelInn?: string;
  
  // Данные покупателя
  buyerName: string;
  buyerPassport: string;
  buyerAddress: string;
  buyerPhone: string;
  buyerEmail: string;
  
  // Данные о щенке
  dogName: string;
  dogBreed: string;
  dogBirthDate: string;
  dogGender: string;
  dogColor: string;
  dogChipNumber?: string;
  dogPedigree?: string;
  
  // Финансовые условия
  price: string;
  prepayment?: string;
  paymentMethod: string;
  
  // Дополнительные условия
  additionalTerms?: string;
  
  // Дата договора
  contractDate: string;
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
    kennelName: "GREAT LEGACY BULLY",
    kennelOwner: "",
    kennelAddress: "",
    kennelPhone: "",
    kennelEmail: "",
    kennelInn: "",
    buyerName: "",
    buyerPassport: "",
    buyerAddress: "",
    buyerPhone: "",
    buyerEmail: "",
    dogName: "",
    dogBreed: "American Bully",
    dogBirthDate: "",
    dogGender: "",
    dogColor: "",
    dogChipNumber: "",
    dogPedigree: "",
    price: "",
    prepayment: "",
    paymentMethod: "Наличные",
    additionalTerms: "",
    contractDate: new Date().toISOString().split('T')[0],
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
    formData.append("file", file);

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

  const handleChange = (field: keyof ContractData, value: string) => {
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
        // Очистка формы
        setFormData({
          ...formData,
          buyerName: "",
          buyerPassport: "",
          buyerAddress: "",
          buyerPhone: "",
          buyerEmail: "",
          dogName: "",
          dogBirthDate: "",
          dogGender: "",
          dogColor: "",
          dogChipNumber: "",
          dogPedigree: "",
          price: "",
          prepayment: "",
          additionalTerms: "",
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
        <h1>ДОГОВОР КУПЛИ-ПРОДАЖИ ЩЕНКА</h1>
        <p style="text-align: center;">№ ____ от ${formData.contractDate}</p>
        
        <div class="section">
          <h3>1. ПРОДАВЕЦ (Питомник)</h3>
          <div class="field"><span class="label">Название:</span> ${formData.kennelName}</div>
          <div class="field"><span class="label">Владелец:</span> ${formData.kennelOwner}</div>
          <div class="field"><span class="label">Адрес:</span> ${formData.kennelAddress}</div>
          <div class="field"><span class="label">Телефон:</span> ${formData.kennelPhone}</div>
          <div class="field"><span class="label">Email:</span> ${formData.kennelEmail}</div>
          ${formData.kennelInn ? `<div class="field"><span class="label">ИНН:</span> ${formData.kennelInn}</div>` : ''}
        </div>

        <div class="section">
          <h3>2. ПОКУПАТЕЛЬ</h3>
          <div class="field"><span class="label">ФИО:</span> ${formData.buyerName}</div>
          <div class="field"><span class="label">Паспорт:</span> ${formData.buyerPassport}</div>
          <div class="field"><span class="label">Адрес:</span> ${formData.buyerAddress}</div>
          <div class="field"><span class="label">Телефон:</span> ${formData.buyerPhone}</div>
          <div class="field"><span class="label">Email:</span> ${formData.buyerEmail}</div>
        </div>

        <div class="section">
          <h3>3. ПРЕДМЕТ ДОГОВОРА</h3>
          <p>Продавец передает в собственность, а Покупатель принимает и оплачивает щенка со следующими характеристиками:</p>
          <div class="field"><span class="label">Кличка:</span> ${formData.dogName}</div>
          <div class="field"><span class="label">Порода:</span> ${formData.dogBreed}</div>
          <div class="field"><span class="label">Дата рождения:</span> ${formData.dogBirthDate}</div>
          <div class="field"><span class="label">Пол:</span> ${formData.dogGender}</div>
          <div class="field"><span class="label">Окрас:</span> ${formData.dogColor}</div>
          ${formData.dogChipNumber ? `<div class="field"><span class="label">№ чипа:</span> ${formData.dogChipNumber}</div>` : ''}
          ${formData.dogPedigree ? `<div class="field"><span class="label">Родословная:</span> ${formData.dogPedigree}</div>` : ''}
        </div>

        <div class="section">
          <h3>4. СТОИМОСТЬ И ПОРЯДОК ОПЛАТЫ</h3>
          <div class="field"><span class="label">Стоимость щенка:</span> ${formData.price} руб.</div>
          ${formData.prepayment ? `<div class="field"><span class="label">Предоплата:</span> ${formData.prepayment} руб.</div>` : ''}
          <div class="field"><span class="label">Способ оплаты:</span> ${formData.paymentMethod}</div>
        </div>

        ${formData.additionalTerms ? `
        <div class="section">
          <h3>5. ДОПОЛНИТЕЛЬНЫЕ УСЛОВИЯ</h3>
          <p>${formData.additionalTerms}</p>
        </div>
        ` : ''}

        <div class="signature">
          <div class="signature-block">
            <p>ПРОДАВЕЦ</p>
            <p>_________________</p>
            <p>${formData.kennelOwner}</p>
          </div>
          <div class="signature-block">
            <p>ПОКУПАТЕЛЬ</p>
            <p>_________________</p>
            <p>${formData.buyerName}</p>
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
              <h2 className="text-xl font-semibold mb-4">Данные питомника</h2>
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
                  <label className="block text-sm font-medium mb-1">Владелец питомника *</label>
                  <Input
                    value={formData.kennelOwner}
                    onChange={(e) => handleChange('kennelOwner', e.target.value)}
                    placeholder="Иванов Иван Иванович"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Адрес</label>
                  <Input
                    value={formData.kennelAddress}
                    onChange={(e) => handleChange('kennelAddress', e.target.value)}
                    placeholder="г. Москва, ул. ..."
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Телефон</label>
                  <Input
                    value={formData.kennelPhone}
                    onChange={(e) => handleChange('kennelPhone', e.target.value)}
                    placeholder="+7 (___) ___-__-__"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Email</label>
                  <Input
                    value={formData.kennelEmail}
                    onChange={(e) => handleChange('kennelEmail', e.target.value)}
                    placeholder="info@kennel.ru"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">ИНН (опционально)</label>
                  <Input
                    value={formData.kennelInn}
                    onChange={(e) => handleChange('kennelInn', e.target.value)}
                    placeholder="1234567890"
                  />
                </div>
              </div>
            </div>

            <div className="bg-card border border-border rounded-lg p-6">
              <h2 className="text-xl font-semibold mb-4">Данные покупателя</h2>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium mb-1">ФИО покупателя *</label>
                  <Input
                    value={formData.buyerName}
                    onChange={(e) => handleChange('buyerName', e.target.value)}
                    placeholder="Петров Петр Петрович"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Паспортные данные</label>
                  <Input
                    value={formData.buyerPassport}
                    onChange={(e) => handleChange('buyerPassport', e.target.value)}
                    placeholder="1234 567890 выдан ..."
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Адрес</label>
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
                <div className="col-span-2">
                  <label className="block text-sm font-medium mb-1">Email покупателя *</label>
                  <Input
                    value={formData.buyerEmail}
                    onChange={(e) => handleChange('buyerEmail', e.target.value)}
                    placeholder="buyer@email.com"
                  />
                </div>
              </div>
            </div>

            <div className="bg-card border border-border rounded-lg p-6">
              <h2 className="text-xl font-semibold mb-4">Данные о щенке</h2>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium mb-1">Кличка *</label>
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
                    placeholder="American Bully"
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
                  <label className="block text-sm font-medium mb-1">Номер чипа (опционально)</label>
                  <Input
                    value={formData.dogChipNumber}
                    onChange={(e) => handleChange('dogChipNumber', e.target.value)}
                    placeholder="123456789012345"
                  />
                </div>
                <div className="col-span-2">
                  <label className="block text-sm font-medium mb-1">Родословная (опционально)</label>
                  <Input
                    value={formData.dogPedigree}
                    onChange={(e) => handleChange('dogPedigree', e.target.value)}
                    placeholder="ABKC/UKC номер"
                  />
                </div>
              </div>
            </div>

            <div className="bg-card border border-border rounded-lg p-6">
              <h2 className="text-xl font-semibold mb-4">Финансовые условия</h2>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium mb-1">Стоимость (руб.) *</label>
                  <Input
                    type="number"
                    value={formData.price}
                    onChange={(e) => handleChange('price', e.target.value)}
                    placeholder="150000"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Предоплата (руб.)</label>
                  <Input
                    type="number"
                    value={formData.prepayment}
                    onChange={(e) => handleChange('prepayment', e.target.value)}
                    placeholder="30000"
                  />
                </div>
                <div className="col-span-2">
                  <label className="block text-sm font-medium mb-1">Способ оплаты</label>
                  <select
                    value={formData.paymentMethod}
                    onChange={(e) => handleChange('paymentMethod', e.target.value)}
                    className="w-full bg-background border border-border px-3 py-2 rounded"
                  >
                    <option value="Наличные">Наличные</option>
                    <option value="Безналичный расчет">Безналичный расчет</option>
                    <option value="Комбинированный">Комбинированный</option>
                  </select>
                </div>
              </div>
            </div>

            <div className="bg-card border border-border rounded-lg p-6">
              <h2 className="text-xl font-semibold mb-4">Дополнительные условия</h2>
              <Textarea
                value={formData.additionalTerms}
                onChange={(e) => handleChange('additionalTerms', e.target.value)}
                placeholder="Укажите дополнительные условия договора, гарантии, особые требования..."
                className="min-h-32"
              />
            </div>

            <div className="bg-card border border-border rounded-lg p-6">
              <h2 className="text-xl font-semibold mb-4">Дата договора</h2>
              <Input
                type="date"
                value={formData.contractDate}
                onChange={(e) => handleChange('contractDate', e.target.value)}
                className="max-w-xs"
              />
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
