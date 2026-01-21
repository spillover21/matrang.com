import { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { LogOut, Save, Upload, Key, Plus, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { Checkbox } from "@/components/ui/checkbox";
import { Slider } from "@/components/ui/slider";

interface ContentData {
  [key: string]: any;
}

interface AdminDashboardProps {
  token: string;
  onLogout: () => void;
}

const AdminDashboard = ({ token, onLogout }: AdminDashboardProps) => {
  const [content, setContent] = useState<ContentData>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [activeSection, setActiveSection] = useState("hero");
  const [newPassword, setNewPassword] = useState("");
  const [showPasswordChange, setShowPasswordChange] = useState(false);

  useEffect(() => {
    loadContent();
  }, [token]);

  const loadContent = async () => {
    try {
      const response = await fetch("/api/api.php?action=get");
      const data = await response.json();
      if (data.success) {
        // Добавляем дефолтные значения для полей размера шрифта, если их нет
        const loadedData = data.data;
        
        // Header defaults
        if (loadedData.header) {
          if (loadedData.header.logoTextSize === undefined) {
            loadedData.header.logoTextSize = 30;
          }
          if (loadedData.header.taglineSize === undefined) {
            loadedData.header.taglineSize = 12;
          }
        }
        
        // Hero defaults
        if (loadedData.hero) {
          if (loadedData.hero.titleSize === undefined) {
            loadedData.hero.titleSize = 80;
          }
          // Если есть старое поле title, конвертируем в titles
          if (loadedData.hero.title && !loadedData.hero.titles) {
            loadedData.hero.titles = [
              {
                text: loadedData.hero.title,
                size: loadedData.hero.titleSize || 80
              }
            ];
          }
          // Если нет titles, создаем пустой массив
          if (!loadedData.hero.titles) {
            loadedData.hero.titles = [
              { text: 'Заголовок', size: 80 }
            ];
          }
        }
        
        // Gallery defaults
        if (loadedData.gallery) {
          if (!loadedData.gallery.availableText) {
            loadedData.gallery.availableText = "В продаже";
          }
          if (!loadedData.gallery.notAvailableText) {
            loadedData.gallery.notAvailableText = "Производитель";
          }
        }
        
        setContent(loadedData);
      }
    } catch (error) {
      toast.error("Ошибка при загрузке контента");
      console.error(error);
    } finally {
      setLoading(false);
    }
  };

  const saveContent = async () => {
    setSaving(true);
    try {
      const response = await fetch("/api/api.php?action=save", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify(content),
      });

      const data = await response.json();
      if (data.success) {
        toast.success("Контент сохранён успешно!");
      } else {
        toast.error("Ошибка при сохранении");
      }
    } catch (error) {
      toast.error("Ошибка при подключении");
      console.error(error);
    } finally {
      setSaving(false);
    }
  };

  const handleImageUpload = async (
    e: React.ChangeEvent<HTMLInputElement>,
    section: string,
    field: string
  ) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append("file", file);

    try {
      const response = await fetch("/api/api.php?action=upload", {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
        },
        body: formData,
      });

      const data = await response.json();
      if (data.success) {
        setContent((prev) => ({
          ...prev,
          [section]: {
            ...prev[section],
            [field]: data.url,
          },
        }));
        toast.success("Изображение загружено!");
      } else {
        toast.error("Ошибка при загрузке изображения");
      }
    } catch (error) {
      toast.error("Ошибка при подключении");
      console.error(error);
    }
  };

  const handleTextChange = (
    section: string,
    field: string,
    value: any
  ) => {
    setContent((prev) => ({
      ...prev,
      [section]: {
        ...prev[section],
        [field]: value,
      },
    }));
  };

  const handleArrayItemChange = (
    section: string,
    field: string,
    index: number,
    itemField: string,
    value: any
  ) => {
    setContent((prev) => {
      const array = [...(prev[section]?.[field] || [])];
      array[index] = {
        ...array[index],
        [itemField]: value,
      };
      return {
        ...prev,
        [section]: {
          ...prev[section],
          [field]: array,
        },
      };
    });
  };

  const handleAddArrayItem = (section: string, field: string, template: any) => {
    setContent((prev) => {
      const array = [...(prev[section]?.[field] || [])];
      array.push(template);
      return {
        ...prev,
        [section]: {
          ...prev[section],
          [field]: array,
        },
      };
    });
  };

  const handleRemoveArrayItem = (section: string, field: string, index: number) => {
    setContent((prev) => {
      const array = [...(prev[section]?.[field] || [])];
      array.splice(index, 1);
      return {
        ...prev,
        [section]: {
          ...prev[section],
          [field]: array,
        },
      };
    });
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin w-12 h-12 border-4 border-primary border-t-transparent rounded-full mx-auto mb-4"></div>
          <p>Загрузка...</p>
        </div>
      </div>
    );
  }

  const sectionDataRaw = content[activeSection] || {};
  const sectionData = activeSection === "header"
    ? { favicon: sectionDataRaw.favicon || "", ...sectionDataRaw }
    : sectionDataRaw;

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <div className="border-b border-border bg-card sticky top-0 z-40">
        <div className="container mx-auto px-4 py-4 flex items-center justify-between">
          <h1 className="text-2xl font-bold">Админ Панель - MATRANG</h1>
          <div className="flex items-center gap-4">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setShowPasswordChange(!showPasswordChange)}
            >
              <Key className="w-4 h-4 mr-2" />
              Сменить пароль
            </Button>
            <Button variant="destructive" size="sm" onClick={onLogout}>
              <LogOut className="w-4 h-4 mr-2" />
              Выход
            </Button>
          </div>
        </div>
      </div>

      {/* Password Change Modal */}
      {showPasswordChange && (
        <div className="border-b border-border bg-card">
          <div className="container mx-auto px-4 py-4">
            <div className="max-w-md">
              <p className="text-sm mb-2">Новый пароль:</p>
              <div className="flex gap-2">
                <Input
                  type="password"
                  placeholder="Введите новый пароль"
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                />
                <Button
                  onClick={() => {
                    // Здесь нужно добавить логику смены пароля на сервере
                    toast.info("Функция смены пароля будет реализована");
                    setNewPassword("");
                    setShowPasswordChange(false);
                  }}
                >
                  Сохранить
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}

      <div className="container mx-auto px-4 py-8">
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
          {/* Sidebar */}
          <div className="lg:col-span-1">
            <div className="bg-card border border-border rounded-lg p-4 sticky top-24">
              <h2 className="font-bold mb-4">Секции</h2>
              <div className="space-y-2">
                {Object.keys(content).map((section) => (
                  <button
                    key={section}
                    onClick={() => setActiveSection(section)}
                    className={`w-full text-left px-4 py-2 rounded transition-colors ${
                      activeSection === section
                        ? "bg-primary text-primary-foreground"
                        : "bg-background hover:bg-muted"
                    }`}
                  >
                    {section.charAt(0).toUpperCase() + section.slice(1)}
                  </button>
                ))}
              </div>
            </div>
          </div>

          {/* Main Content */}
          <div className="lg:col-span-3">
            <div className="bg-card border border-border rounded-lg p-6 mb-6">
              <h2 className="text-2xl font-bold mb-6">
                {activeSection.charAt(0).toUpperCase() + activeSection.slice(1)}
              </h2>

              <div className="space-y-6">
                {Object.entries(sectionData)
                  .filter(([field]) => {
                    // Скрываем служебные поля для изображений hero, которые уже обрабатываются в блоке image
                    if (activeSection === 'hero' && ['imageZoom', 'imageHeight', 'imagePositionX', 'imagePositionY'].includes(field)) {
                      return false;
                    }
                    // Скрываем старое поле dogs если есть categories
                    if (activeSection === 'gallery' && field === 'dogs' && sectionData.categories) {
                      return false;
                    }
                    return true;
                  })
                  .map(([field, value]) => (
                  <div key={field}>
                    <label className="block text-sm font-medium mb-2 capitalize">
                      {field === 'stats' ? 'Статистика' : 
                       field === 'features' ? 'Преимущества (карточки)' :
                       field === 'dogs' ? 'Собаки в галерее' :
                       field === 'categories' ? 'Категории галереи' :
                       field === 'social' ? 'Социальные сети и контакты' :
                       field === 'items' && activeSection === 'testimonials' ? 'Отзывы' :
                       field === 'locations' ? 'Города доставки' :
                       field === 'logoTextSize' ? 'Размер названия логотипа' :
                       field === 'taglineSize' ? 'Размер подзаголовка' :
                       field === 'titleSize' ? 'Размер заголовка Hero' :
                       field === 'titles' ? 'Заголовки Hero (множественные)' :
                       field}
                    </label>

                    {/* STATS - Статистика */}
                    {field === 'stats' && Array.isArray(value) ? (
                      <div className="space-y-4">
                        {value.map((stat: any, index: number) => (
                          <div key={index} className="p-4 bg-secondary border border-border rounded">
                            <div className="flex items-center justify-between mb-3">
                              <h4 className="font-semibold">Статистика {index + 1}</h4>
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => handleRemoveArrayItem(activeSection, field, index)}
                              >
                                <Trash2 className="w-4 h-4 text-destructive" />
                              </Button>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block">Значение (например: 15+)</label>
                                <Input
                                  value={stat.value || ''}
                                  onChange={(e) => handleArrayItemChange(activeSection, field, index, 'value', e.target.value)}
                                  placeholder="15+"
                                />
                              </div>
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block">Описание (например: Лет опыта)</label>
                                <Input
                                  value={stat.label || ''}
                                  onChange={(e) => handleArrayItemChange(activeSection, field, index, 'label', e.target.value)}
                                  placeholder="Лет опыта"
                                />
                              </div>
                            </div>
                          </div>
                        ))}
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleAddArrayItem(activeSection, field, { value: '', label: '' })}
                          className="w-full"
                        >
                          <Plus className="w-4 h-4 mr-2" />
                          Добавить статистику
                        </Button>
                      </div>

                    /* FEATURES - Преимущества */
                    ) : field === 'features' && Array.isArray(value) ? (
                      <div className="space-y-4">
                        {value.map((feature: any, index: number) => (
                          <div key={index} className="p-4 bg-secondary border border-border rounded">
                            <div className="flex items-center justify-between mb-3">
                              <h4 className="font-semibold">Карточка {index + 1}</h4>
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => handleRemoveArrayItem(activeSection, field, index)}
                              >
                                <Trash2 className="w-4 h-4 text-destructive" />
                              </Button>
                            </div>
                            <div className="space-y-3">
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block">Иконка</label>
                                <select
                                  value={feature.icon || 'Shield'}
                                  onChange={(e) => handleArrayItemChange(activeSection, field, index, 'icon', e.target.value)}
                                  className="w-full bg-background border border-border px-3 py-2 rounded"
                                >
                                  <option value="Shield">🛡️ Щит (Shield)</option>
                                  <option value="Heart">❤️ Сердце (Heart)</option>
                                  <option value="Zap">⚡ Молния (Zap)</option>
                                  <option value="Award">🏆 Награда (Award)</option>
                                </select>
                              </div>
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-2">
                                  🇷🇺 Заголовок (Русский)
                                </label>
                                <Input
                                  value={typeof feature.title === 'string' ? feature.title : feature.title?.ru || ''}
                                  onChange={(e) => {
                                    const newTitle = typeof feature.title === 'string' 
                                      ? { ru: e.target.value, en: '' }
                                      : { ...feature.title, ru: e.target.value };
                                    handleArrayItemChange(activeSection, field, index, 'title', newTitle);
                                  }}
                                  placeholder="Защитник"
                                />
                              </div>
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-2">
                                  🇬🇧 Title (English)
                                </label>
                                <Input
                                  value={typeof feature.title === 'object' ? feature.title?.en || '' : ''}
                                  onChange={(e) => {
                                    const newTitle = typeof feature.title === 'string'
                                      ? { ru: feature.title, en: e.target.value }
                                      : { ...feature.title, en: e.target.value };
                                    handleArrayItemChange(activeSection, field, index, 'title', newTitle);
                                  }}
                                  placeholder="Protector"
                                />
                              </div>
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-2">
                                  🇷🇺 Описание (Русский)
                                </label>
                                <Textarea
                                  value={typeof feature.description === 'string' ? feature.description : feature.description?.ru || ''}
                                  onChange={(e) => {
                                    const newDesc = typeof feature.description === 'string'
                                      ? { ru: e.target.value, en: '' }
                                      : { ...feature.description, ru: e.target.value };
                                    handleArrayItemChange(activeSection, field, index, 'description', newDesc);
                                  }}
                                  placeholder="Непоколебимая преданность..."
                                  className="min-h-20"
                                />
                              </div>
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-2">
                                  🇬🇧 Description (English)
                                </label>
                                <Textarea
                                  value={typeof feature.description === 'object' ? feature.description?.en || '' : ''}
                                  onChange={(e) => {
                                    const newDesc = typeof feature.description === 'string'
                                      ? { ru: feature.description, en: e.target.value }
                                      : { ...feature.description, en: e.target.value };
                                    handleArrayItemChange(activeSection, field, index, 'description', newDesc);
                                  }}
                                  placeholder="Unwavering loyalty..."
                                  className="min-h-20"
                                />
                              </div>
                            </div>
                          </div>
                        ))}
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleAddArrayItem(activeSection, field, { icon: 'Shield', title: '', description: '' })}
                          className="w-full"
                        >
                          <Plus className="w-4 h-4 mr-2" />
                          Добавить карточку
                        </Button>
                      </div>

                    /* DOGS - Собаки */
                    ) : field === 'dogs' && Array.isArray(value) ? (
                      <div className="space-y-4">
                        {value.map((dog: any, index: number) => (
                          <div key={index} className="p-4 bg-secondary border border-border rounded">
                            <div className="flex items-center justify-between mb-3">
                              <h4 className="font-semibold">Собака {index + 1}: {dog.name || 'Без имени'}</h4>
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => handleRemoveArrayItem(activeSection, field, index)}
                              >
                                <Trash2 className="w-4 h-4 text-destructive" />
                              </Button>
                            </div>
                            <div className="space-y-3">
                              <div className="grid grid-cols-2 gap-3">
                                <div>
                                  <label className="text-xs text-muted-foreground mb-1 block">Кличка</label>
                                  <Input
                                    value={dog.name || ''}
                                    onChange={(e) => handleArrayItemChange(activeSection, field, index, 'name', e.target.value)}
                                    placeholder="TITAN"
                                  />
                                </div>
                                <div>
                                  <label className="text-xs text-muted-foreground mb-1 block">Возраст</label>
                                  <Input
                                    value={dog.age || ''}
                                    onChange={(e) => handleArrayItemChange(activeSection, field, index, 'age', e.target.value)}
                                    placeholder="8 месяцев"
                                  />
                                </div>
                              </div>
                              <div className="grid grid-cols-2 gap-3">
                                <div>
                                  <label className="text-xs text-muted-foreground mb-1 block">Окрас</label>
                                  <Input
                                    value={dog.color || ''}
                                    onChange={(e) => handleArrayItemChange(activeSection, field, index, 'color', e.target.value)}
                                    placeholder="Blue Fawn"
                                  />
                                </div>
                                <div>
                                  <label className="text-xs text-muted-foreground mb-1 block">Цена</label>
                                  <Input
                                    value={dog.price || ''}
                                    onChange={(e) => handleArrayItemChange(activeSection, field, index, 'price', e.target.value)}
                                    placeholder="150 000 ₽"
                                  />
                                </div>
                              </div>
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block">Фото собаки</label>
                                {dog.image && (
                                  <div className="mb-3 relative">
                                    <div 
                                      className="w-full aspect-square rounded border border-border overflow-hidden bg-card"
                                      style={{
                                        WebkitMaskImage: 'radial-gradient(ellipse 85% 85% at 50% 50%, black 50%, rgba(0,0,0,0.3) 85%, transparent 100%)',
                                        maskImage: 'radial-gradient(ellipse 85% 85% at 50% 50%, black 50%, rgba(0,0,0,0.3) 85%, transparent 100%)'
                                      }}
                                    >
                                      <div 
                                        className="w-full h-full"
                                        style={{
                                          transform: `scale(${(dog.imageZoom || 100) / 100}, ${(dog.imageHeight || 100) / 100})`,
                                          transformOrigin: `${dog.imagePositionX || 50}% ${dog.imagePositionY || 50}%`
                                        }}
                                      >
                                        <img
                                          src={dog.image}
                                          alt={dog.name || 'Собака'}
                                          className="w-full h-full object-contain"
                                          style={{
                                            objectPosition: `${dog.imagePositionX || 50}% ${dog.imagePositionY || 50}%`
                                          }}
                                        />
                                      </div>
                                    </div>
                                    
                                    {/* Настройки позиции и зума */}
                                    <div className="mt-3 space-y-3 p-3 bg-background rounded border border-border">
                                      <div>
                                        <label className="text-xs text-muted-foreground mb-2 block">
                                          Ширина (зум): {dog.imageZoom || 100}%
                                        </label>
                                        <Slider
                                          value={[dog.imageZoom || 100]}
                                          onValueChange={(value) => handleArrayItemChange(activeSection, field, index, 'imageZoom', value[0])}
                                          min={50}
                                          max={200}
                                          step={5}
                                          className="w-full"
                                        />
                                      </div>
                                      <div>
                                        <label className="text-xs text-muted-foreground mb-2 block">
                                          Высота: {dog.imageHeight || 100}%
                                        </label>
                                        <Slider
                                          value={[dog.imageHeight || 100]}
                                          onValueChange={(value) => handleArrayItemChange(activeSection, field, index, 'imageHeight', value[0])}
                                          min={50}
                                          max={200}
                                          step={5}
                                          className="w-full"
                                        />
                                      </div>
                                      <div>
                                        <label className="text-xs text-muted-foreground mb-2 block">
                                          Позиция по горизонтали: {dog.imagePositionX || 50}%
                                        </label>
                                        <Slider
                                          value={[dog.imagePositionX || 50]}
                                          onValueChange={(value) => handleArrayItemChange(activeSection, field, index, 'imagePositionX', value[0])}
                                          min={0}
                                          max={100}
                                          step={5}
                                          className="w-full"
                                        />
                                      </div>
                                      <div>
                                        <label className="text-xs text-muted-foreground mb-2 block">
                                          Позиция по вертикали: {dog.imagePositionY || 50}%
                                        </label>
                                        <Slider
                                          value={[dog.imagePositionY || 50]}
                                          onValueChange={(value) => handleArrayItemChange(activeSection, field, index, 'imagePositionY', value[0])}
                                          min={0}
                                          max={100}
                                          step={5}
                                          className="w-full"
                                        />
                                      </div>
                                    </div>
                                  </div>
                                )}
                                <label className="flex items-center justify-center gap-2 p-3 border-2 border-dashed border-border rounded cursor-pointer hover:bg-muted transition-colors">
                                  <Upload className="w-4 h-4" />
                                  <span className="text-sm">{dog.image ? 'Изменить фото' : 'Загрузить фото'}</span>
                                  <input
                                    type="file"
                                    accept="image/*"
                                    onChange={(e) => {
                                      const file = e.target.files?.[0];
                                      if (file) {
                                        const formData = new FormData();
                                        formData.append("file", file);
                                        fetch("/api/api.php?action=upload", {
                                          method: "POST",
                                          headers: { Authorization: `Bearer ${token}` },
                                          body: formData,
                                        })
                                          .then((res) => res.json())
                                          .then((data) => {
                                            if (data.success) {
                                              handleArrayItemChange(activeSection, field, index, 'image', data.url);
                                              toast.success("Фото загружено!");
                                            } else {
                                              toast.error("Ошибка загрузки фото");
                                            }
                                          })
                                          .catch(() => toast.error("Ошибка подключения"));
                                      }
                                    }}
                                    className="hidden"
                                  />
                                </label>
                              </div>
                              <div className="flex items-center gap-2">
                                <Checkbox
                                  checked={dog.available || false}
                                  onCheckedChange={(checked) => handleArrayItemChange(activeSection, field, index, 'available', checked)}
                                />
                                <label className="text-sm">В продаже</label>
                              </div>
                            </div>
                          </div>
                        ))}
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleAddArrayItem(activeSection, field, { 
                            id: Date.now(), 
                            name: '', 
                            age: '', 
                            color: '', 
                            price: '', 
                            image: '', 
                            imageZoom: 100,
                            imageHeight: 100,
                            imagePositionX: 50,
                            imagePositionY: 50,
                            available: true 
                          })}
                          className="w-full"
                        >
                          <Plus className="w-4 h-4 mr-2" />
                          Добавить собаку
                        </Button>
                      </div>

                    /* SOCIAL - Соцсети */
                    ) : field === 'social' && Array.isArray(value) ? (
                      <div className="space-y-4">
                        {value.map((social: any, index: number) => (
                          <div key={index} className="p-4 bg-secondary border border-border rounded">
                            <div className="flex items-center justify-between mb-3">
                              <h4 className="font-semibold">{social.label || `Соцсеть ${index + 1}`}</h4>
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => handleRemoveArrayItem(activeSection, field, index)}
                              >
                                <Trash2 className="w-4 h-4 text-destructive" />
                              </Button>
                            </div>
                            <div className="space-y-3">
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block">Иконка</label>
                                <select
                                  value={social.icon || 'MessageCircle'}
                                  onChange={(e) => handleArrayItemChange(activeSection, field, index, 'icon', e.target.value)}
                                  className="w-full bg-background border border-border px-3 py-2 rounded"
                                >
                                  <option value="MessageCircle">💬 WhatsApp</option>
                                  <option value="Instagram">📷 Instagram</option>
                                  <option value="Youtube">▶️ YouTube</option>
                                  <option value="Phone">📱 Телефон</option>
                                  <option value="Mail">✉️ Email</option>
                                  <option value="MapPin">📍 Адрес</option>
                                </select>
                              </div>
                              <div className="grid grid-cols-2 gap-3">
                                <div>
                                  <label className="text-xs text-muted-foreground mb-1 block">Название</label>
                                  <Input
                                    value={social.label || ''}
                                    onChange={(e) => handleArrayItemChange(activeSection, field, index, 'label', e.target.value)}
                                    placeholder="Instagram"
                                  />
                                </div>
                                <div>
                                  <label className="text-xs text-muted-foreground mb-1 block">Значение</label>
                                  <Input
                                    value={social.value || ''}
                                    onChange={(e) => handleArrayItemChange(activeSection, field, index, 'value', e.target.value)}
                                    placeholder="@matrang_dogs"
                                  />
                                </div>
                              </div>
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block">Ссылка</label>
                                <Input
                                  value={social.link || ''}
                                  onChange={(e) => handleArrayItemChange(activeSection, field, index, 'link', e.target.value)}
                                  placeholder="https://instagram.com/matrang_dogs"
                                />
                              </div>
                            </div>
                          </div>
                        ))}
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleAddArrayItem(activeSection, field, { icon: 'MessageCircle', label: '', value: '', link: '#' })}
                          className="w-full"
                        >
                          <Plus className="w-4 h-4 mr-2" />
                          Добавить соцсеть
                        </Button>
                      </div>

                    /* CATEGORIES - Категории галереи */
                    ) : field === 'categories' && Array.isArray(value) ? (
                      <div className="space-y-4">
                        {value.map((category: any, catIndex: number) => (
                          <div key={catIndex} className="p-4 bg-muted border-2 border-border rounded-lg">
                            <div className="flex items-center justify-between mb-4">
                              <Input
                                value={category.name || ''}
                                onChange={(e) => handleArrayItemChange(activeSection, field, catIndex, 'name', e.target.value)}
                                placeholder="Название категории"
                                className="text-lg font-semibold flex-1 mr-3"
                              />
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => handleRemoveArrayItem(activeSection, field, catIndex)}
                              >
                                <Trash2 className="w-4 h-4 text-destructive" />
                              </Button>
                            </div>
                            
                            {/* Собаки в категории */}
                            <div className="space-y-3 ml-4 border-l-2 border-primary/30 pl-4">
                              <h5 className="text-sm font-semibold text-muted-foreground mb-2">Собаки в категории:</h5>
                              {(category.dogs || []).map((dog: any, dogIndex: number) => (
                                <div key={dogIndex} className="p-3 bg-background border border-border rounded">
                                  <div className="flex items-center justify-between mb-3">
                                    <h5 className="font-semibold text-sm">Собака {dogIndex + 1}: {dog.name || 'Без имени'}</h5>
                                    <Button
                                      variant="ghost"
                                      size="sm"
                                      onClick={() => {
                                        const updatedCategory = { ...category };
                                        updatedCategory.dogs = [...category.dogs];
                                        updatedCategory.dogs.splice(dogIndex, 1);
                                        handleArrayItemChange(activeSection, field, catIndex, 'dogs', updatedCategory.dogs);
                                      }}
                                    >
                                      <Trash2 className="w-3 h-3 text-destructive" />
                                    </Button>
                                  </div>
                                  <div className="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                      <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-1">
                                        🇷🇺 Кличка
                                      </label>
                                      <Input
                                        value={typeof dog.name === 'string' ? dog.name : dog.name?.ru || ''}
                                        onChange={(e) => {
                                          const updatedDogs = [...category.dogs];
                                          const newName = typeof dog.name === 'string' 
                                            ? { ru: e.target.value, en: '' }
                                            : { ...dog.name, ru: e.target.value };
                                          updatedDogs[dogIndex] = { ...dog, name: newName };
                                          handleArrayItemChange(activeSection, field, catIndex, 'dogs', updatedDogs);
                                        }}
                                        placeholder="MATRANG"
                                        className="h-8"
                                      />
                                    </div>
                                    <div>
                                      <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-1">
                                        🇬🇧 Name
                                      </label>
                                      <Input
                                        value={typeof dog.name === 'object' ? dog.name?.en || '' : ''}
                                        onChange={(e) => {
                                          const updatedDogs = [...category.dogs];
                                          const newName = typeof dog.name === 'string'
                                            ? { ru: dog.name, en: e.target.value }
                                            : { ...dog.name, en: e.target.value };
                                          updatedDogs[dogIndex] = { ...dog, name: newName };
                                          handleArrayItemChange(activeSection, field, catIndex, 'dogs', updatedDogs);
                                        }}
                                        placeholder="MATRANG"
                                        className="h-8"
                                      />
                                    </div>
                                    <div>
                                      <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-1">
                                        🇷🇺 Возраст
                                      </label>
                                      <Input
                                        value={typeof dog.age === 'string' ? dog.age : dog.age?.ru || ''}
                                        onChange={(e) => {
                                          const updatedDogs = [...category.dogs];
                                          const newAge = typeof dog.age === 'string'
                                            ? { ru: e.target.value, en: '' }
                                            : { ...dog.age, ru: e.target.value };
                                          updatedDogs[dogIndex] = { ...dog, age: newAge };
                                          handleArrayItemChange(activeSection, field, catIndex, 'dogs', updatedDogs);
                                        }}
                                        placeholder="2 года"
                                        className="h-8"
                                      />
                                    </div>
                                    <div>
                                      <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-1">
                                        🇬🇧 Age
                                      </label>
                                      <Input
                                        value={typeof dog.age === 'object' ? dog.age?.en || '' : ''}
                                        onChange={(e) => {
                                          const updatedDogs = [...category.dogs];
                                          const newAge = typeof dog.age === 'string'
                                            ? { ru: dog.age, en: e.target.value }
                                            : { ...dog.age, en: e.target.value };
                                          updatedDogs[dogIndex] = { ...dog, age: newAge };
                                          handleArrayItemChange(activeSection, field, catIndex, 'dogs', updatedDogs);
                                        }}
                                        placeholder="2 years"
                                        className="h-8"
                                      />
                                    </div>
                                    <div>
                                      <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-1">
                                        🇷🇺 Окрас
                                      </label>
                                      <Input
                                        value={typeof dog.color === 'string' ? dog.color : dog.color?.ru || ''}
                                        onChange={(e) => {
                                          const updatedDogs = [...category.dogs];
                                          const newColor = typeof dog.color === 'string'
                                            ? { ru: e.target.value, en: '' }
                                            : { ...dog.color, ru: e.target.value };
                                          updatedDogs[dogIndex] = { ...dog, color: newColor };
                                          handleArrayItemChange(activeSection, field, catIndex, 'dogs', updatedDogs);
                                        }}
                                        placeholder="Lilac blotched"
                                        className="h-8"
                                      />
                                    </div>
                                    <div>
                                      <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-1">
                                        🇬🇧 Color
                                      </label>
                                      <Input
                                        value={typeof dog.color === 'object' ? dog.color?.en || '' : ''}
                                        onChange={(e) => {
                                          const updatedDogs = [...category.dogs];
                                          const newColor = typeof dog.color === 'string'
                                            ? { ru: dog.color, en: e.target.value }
                                            : { ...dog.color, en: e.target.value };
                                          updatedDogs[dogIndex] = { ...dog, color: newColor };
                                          handleArrayItemChange(activeSection, field, catIndex, 'dogs', updatedDogs);
                                        }}
                                        placeholder="Lilac blotched"
                                        className="h-8"
                                      />
                                    </div>
                                    <div className="col-span-2">
                                      <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-1">
                                        🇷🇺 Цена/Статус
                                      </label>
                                      <Input
                                        value={typeof dog.price === 'string' ? dog.price : dog.price?.ru || ''}
                                        onChange={(e) => {
                                          const updatedDogs = [...category.dogs];
                                          const newPrice = typeof dog.price === 'string'
                                            ? { ru: e.target.value, en: '' }
                                            : { ...dog.price, ru: e.target.value };
                                          updatedDogs[dogIndex] = { ...dog, price: newPrice };
                                          handleArrayItemChange(activeSection, field, catIndex, 'dogs', updatedDogs);
                                        }}
                                        placeholder="Производитель"
                                        className="h-8"
                                      />
                                    </div>
                                    <div className="col-span-2">
                                      <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-1">
                                        🇬🇧 Price/Status
                                      </label>
                                      <Input
                                        value={typeof dog.price === 'object' ? dog.price?.en || '' : ''}
                                        onChange={(e) => {
                                          const updatedDogs = [...category.dogs];
                                          const newPrice = typeof dog.price === 'string'
                                            ? { ru: dog.price, en: e.target.value }
                                            : { ...dog.price, en: e.target.value };
                                          updatedDogs[dogIndex] = { ...dog, price: newPrice };
                                          handleArrayItemChange(activeSection, field, catIndex, 'dogs', updatedDogs);
                                        }}
                                        placeholder="Stud Dog"
                                        className="h-8"
                                      />
                                    </div>
                                  </div>
                                  {dog.image && (
                                    <div className="mt-2">
                                      <img src={dog.image} alt={dog.name} className="w-20 h-20 object-cover rounded" />
                                    </div>
                                  )}
                                  <label className="flex items-center justify-center gap-2 mt-2 p-2 border border-dashed border-border rounded cursor-pointer hover:bg-muted/50 transition-colors">
                                    <Upload className="w-3 h-3" />
                                    <span className="text-xs">{dog.image ? 'Изменить фото' : 'Загрузить фото'}</span>
                                    <input
                                      type="file"
                                      accept="image/*"
                                      onChange={(e) => {
                                        const file = e.target.files?.[0];
                                        if (file) {
                                          const formData = new FormData();
                                          formData.append("file", file);
                                          fetch("/api/api.php?action=upload", {
                                            method: "POST",
                                            headers: { Authorization: `Bearer ${token}` },
                                            body: formData,
                                          })
                                            .then((res) => res.json())
                                            .then((data) => {
                                              if (data.success) {
                                                const updatedDogs = [...category.dogs];
                                                updatedDogs[dogIndex] = { ...dog, image: data.url };
                                                handleArrayItemChange(activeSection, field, catIndex, 'dogs', updatedDogs);
                                              }
                                            });
                                        }
                                      }}
                                      className="hidden"
                                    />
                                  </label>
                                </div>
                              ))}
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                  const updatedDogs = [...(category.dogs || []), {
                                    id: Date.now(),
                                    name: '',
                                    age: '',
                                    color: '',
                                    price: '',
                                    image: '',
                                    imageZoom: 100,
                                    imageHeight: 100,
                                    imagePositionX: 50,
                                    imagePositionY: 50,
                                    available: true
                                  }];
                                  handleArrayItemChange(activeSection, field, catIndex, 'dogs', updatedDogs);
                                }}
                                className="w-full"
                              >
                                <Plus className="w-3 h-3 mr-2" />
                                Добавить собаку в категорию
                              </Button>
                            </div>
                          </div>
                        ))}
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleAddArrayItem(activeSection, field, { name: 'Новая категория', dogs: [] })}
                          className="w-full"
                        >
                          <Plus className="w-4 h-4 mr-2" />
                          Добавить категорию
                        </Button>
                      </div>

                    /* TESTIMONIALS - Отзывы */
                    ) : field === 'items' && activeSection === 'testimonials' && Array.isArray(value) ? (
                      <div className="space-y-4">
                        {value.map((testimonial: any, index: number) => (
                          <div key={index} className="p-4 bg-secondary border border-border rounded">
                            <div className="flex items-center justify-between mb-3">
                              <h4 className="font-semibold">Отзыв {index + 1}: {testimonial.title || 'Без названия'}</h4>
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => handleRemoveArrayItem(activeSection, field, index)}
                              >
                                <Trash2 className="w-4 h-4 text-destructive" />
                              </Button>
                            </div>
                            <div className="space-y-3">
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block">Подпись (Имя, город)</label>
                                <Input
                                  value={testimonial.title || ''}
                                  onChange={(e) => handleArrayItemChange(activeSection, field, index, 'title', e.target.value)}
                                  placeholder="Мария, Москва"
                                />
                              </div>
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block">Скриншот отзыва</label>
                                {testimonial.image && (
                                  <div className="mb-3">
                                    <img
                                      src={testimonial.image}
                                      alt={testimonial.title || 'Отзыв'}
                                      className="max-w-full h-auto rounded max-h-64 object-cover"
                                    />
                                  </div>
                                )}
                                <label className="flex items-center justify-center gap-2 p-3 border-2 border-dashed border-border rounded cursor-pointer hover:bg-muted transition-colors">
                                  <Upload className="w-4 h-4" />
                                  <span className="text-sm">{testimonial.image ? 'Изменить скриншот' : 'Загрузить скриншот'}</span>
                                  <input
                                    type="file"
                                    accept="image/*"
                                    onChange={(e) => {
                                      const file = e.target.files?.[0];
                                      if (file) {
                                        const formData = new FormData();
                                        formData.append("file", file);
                                        fetch("/api/api.php?action=upload", {
                                          method: "POST",
                                          headers: { Authorization: `Bearer ${token}` },
                                          body: formData,
                                        })
                                          .then((res) => res.json())
                                          .then((data) => {
                                            if (data.success) {
                                              handleArrayItemChange(activeSection, field, index, 'image', data.url);
                                              toast.success("Скриншот загружен!");
                                            } else {
                                              toast.error("Ошибка загрузки");
                                            }
                                          })
                                          .catch(() => toast.error("Ошибка подключения"));
                                      }
                                    }}
                                    className="hidden"
                                  />
                                </label>
                              </div>
                            </div>
                          </div>
                        ))}
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleAddArrayItem(activeSection, field, { 
                            id: Date.now(), 
                            title: '', 
                            image: '' 
                          })}
                          className="w-full"
                        >
                          <Plus className="w-4 h-4 mr-2" />
                          Добавить отзыв
                        </Button>
                      </div>

                    /* LOCATIONS - География */
                    ) : field === 'locations' && Array.isArray(value) ? (
                      <div className="space-y-4">
                        {value.map((location: any, index: number) => (
                          <div key={index} className="p-4 bg-secondary border border-border rounded">
                            <div className="flex items-center justify-between mb-3">
                              <h4 className="font-semibold">{location.city || `Город ${index + 1}`}</h4>
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => handleRemoveArrayItem(activeSection, field, index)}
                              >
                                <Trash2 className="w-4 h-4 text-destructive" />
                              </Button>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block">Город</label>
                                <Input
                                  value={location.city || ''}
                                  onChange={(e) => handleArrayItemChange(activeSection, field, index, 'city', e.target.value)}
                                  placeholder="Москва"
                                />
                              </div>
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block">Количество щенков</label>
                                <Input
                                  type="number"
                                  value={location.count || ''}
                                  onChange={(e) => handleArrayItemChange(activeSection, field, index, 'count', parseInt(e.target.value) || 0)}
                                  placeholder="10"
                                />
                              </div>
                            </div>
                            <div className="grid grid-cols-2 gap-3 mt-3">
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block">Широта (Latitude)</label>
                                <Input
                                  type="number"
                                  step="0.0001"
                                  value={location.lat || ''}
                                  onChange={(e) => handleArrayItemChange(activeSection, field, index, 'lat', parseFloat(e.target.value) || 0)}
                                  placeholder="55.7558"
                                />
                              </div>
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block">Долгота (Longitude)</label>
                                <Input
                                  type="number"
                                  step="0.0001"
                                  value={location.lng || ''}
                                  onChange={(e) => handleArrayItemChange(activeSection, field, index, 'lng', parseFloat(e.target.value) || 0)}
                                  placeholder="37.6173"
                                />
                              </div>
                            </div>
                            <div className="mt-2">
                              <p className="text-xs text-muted-foreground">
                                💡 Координаты можно найти на <a href="https://www.google.com/maps" target="_blank" rel="noopener noreferrer" className="text-primary underline">Google Maps</a>
                              </p>
                            </div>
                          </div>
                        ))}
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleAddArrayItem(activeSection, field, { 
                            id: Date.now(), 
                            city: '', 
                            count: 1,
                            lat: 55.7558,
                            lng: 37.6173
                          })}
                          className="w-full"
                        >
                          <Plus className="w-4 h-4 mr-2" />
                          Добавить город
                        </Button>
                      </div>

                    /* FONT SIZE SETTINGS */
                    ) : (field === 'logoTextSize' || field === 'taglineSize') && activeSection === 'header' ? (
                      <div className="space-y-3 max-w-md">
                        <div className="text-xs text-muted-foreground">
                          Размер шрифта: {value || (field === 'logoTextSize' ? 30 : 12)}px
                        </div>
                        <Slider
                          value={[value || (field === 'logoTextSize' ? 30 : 12)]}
                          onValueChange={(val) => handleTextChange(activeSection, field, val[0])}
                          min={field === 'logoTextSize' ? 20 : 8}
                          max={field === 'logoTextSize' ? 60 : 24}
                          step={1}
                          className="w-full"
                        />
                        <div className="p-4 bg-secondary rounded border border-border">
                          <div style={{ fontSize: `${value || (field === 'logoTextSize' ? 30 : 12)}px` }}>
                            {field === 'logoTextSize' ? (sectionData.logoText || 'MATRANG DOGS') : (sectionData.tagline || 'GREAT LEGACY BULLY')}
                          </div>
                        </div>
                      </div>

                    /* HERO TITLE SIZE */
                    ) : field === 'titleSize' && activeSection === 'hero' ? (
                      <div className="space-y-3 max-w-md">
                        <div className="text-xs text-muted-foreground">
                          Размер заголовка: {value || 80}px
                        </div>
                        <Slider
                          value={[value || 80]}
                          onValueChange={(val) => handleTextChange(activeSection, field, val[0])}
                          min={40}
                          max={150}
                          step={2}
                          className="w-full"
                        />
                        <div className="p-4 bg-secondary rounded border border-border">
                          <div className="font-display leading-none" style={{ fontSize: `${value || 80}px` }}>
                            {sectionData.title || 'Заголовок'}
                          </div>
                        </div>
                      </div>

                    /* HERO TITLES ARRAY */
                    ) : field === 'titles' && activeSection === 'hero' && Array.isArray(value) ? (
                      <div className="space-y-4">
                        {value.map((titleItem: any, index: number) => (
                          <div key={index} className="p-4 bg-secondary border border-border rounded">
                            <div className="flex items-center justify-between mb-3">
                              <h4 className="font-semibold">Заголовок {index + 1}</h4>
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => handleRemoveArrayItem(activeSection, field, index)}
                              >
                                <Trash2 className="w-4 h-4 text-destructive" />
                              </Button>
                            </div>
                            <div className="space-y-3">
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-2">
                                  🇷🇺 Текст (Русский)
                                </label>
                                <Input
                                  value={typeof titleItem.text === 'string' ? titleItem.text : titleItem.text?.ru || ''}
                                  onChange={(e) => {
                                    const newText = typeof titleItem.text === 'string'
                                      ? { ru: e.target.value, en: '' }
                                      : { ...titleItem.text, ru: e.target.value };
                                    handleArrayItemChange(activeSection, field, index, 'text', newText);
                                  }}
                                  placeholder="Введите текст заголовка на русском"
                                />
                              </div>
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-2">
                                  🇬🇧 Text (English)
                                </label>
                                <Input
                                  value={typeof titleItem.text === 'object' ? titleItem.text?.en || '' : ''}
                                  onChange={(e) => {
                                    const newText = typeof titleItem.text === 'string'
                                      ? { ru: titleItem.text, en: e.target.value }
                                      : { ...titleItem.text, en: e.target.value };
                                    handleArrayItemChange(activeSection, field, index, 'text', newText);
                                  }}
                                  placeholder="Enter title text in English"
                                />
                              </div>
                              <div>
                                <label className="text-xs text-muted-foreground mb-1 block">
                                  Размер шрифта: {titleItem.size || 80}px
                                </label>
                                <Slider
                                  value={[titleItem.size || 80]}
                                  onValueChange={(val) => handleArrayItemChange(activeSection, field, index, 'size', val[0])}
                                  min={40}
                                  max={150}
                                  step={2}
                                  className="w-full"
                                />
                              </div>
                              <div className="p-3 bg-background rounded border border-border">
                                <div className="font-display leading-none" style={{ fontSize: `${titleItem.size || 80}px` }}>
                                  {typeof titleItem.text === 'string' ? titleItem.text : titleItem.text?.ru || 'Предпросмотр'}
                                </div>
                              </div>
                            </div>
                          </div>
                        ))}
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleAddArrayItem(activeSection, field, { 
                            text: 'Новый заголовок', 
                            size: 80 
                          })}
                          className="w-full"
                        >
                          <Plus className="w-4 h-4 mr-2" />
                          Добавить заголовок
                        </Button>
                      </div>

                    /* FAVICON */
                    ) : field === 'favicon' ? (
                      <div className="space-y-3 max-w-sm">
                        <div className="text-xs text-muted-foreground">Рекомендуем 64x64 или 32x32 PNG/WebP. Будет обновлять фавикон сайта.</div>
                        {value && (
                          <div className="w-16 h-16 rounded bg-card border border-border flex items-center justify-center overflow-hidden">
                            <img src={value} alt="Favicon" className="w-full h-full object-contain" />
                          </div>
                        )}
                        <label className="flex items-center justify-center gap-2 p-3 border-2 border-dashed border-border rounded cursor-pointer hover:bg-muted transition-colors">
                          <Upload className="w-4 h-4" />
                          <span className="text-sm">{value ? 'Заменить фавикон' : 'Загрузить фавикон'}</span>
                          <input
                            type="file"
                            accept="image/*"
                            onChange={(e) => handleImageUpload(e, activeSection, field)}
                            className="hidden"
                          />
                        </label>
                      </div>

                    /* Изображения */
                    ) : field.toLowerCase().includes("image") ||
                    field.toLowerCase().includes("photo") ? (
                      activeSection === "hero" && field === "image" ? (
                        <div className="space-y-4">
                          {sectionData.image && (
                            <div className="relative w-full max-w-md aspect-square rounded border border-border overflow-hidden bg-card">
                              <div
                                className="w-full h-full"
                                style={{
                                  transform: `scale(${(sectionData.imageZoom || 100) / 100}, ${(sectionData.imageHeight || 100) / 100})`,
                                  transformOrigin: `${sectionData.imagePositionX || 50}% ${sectionData.imagePositionY || 50}%`,
                                }}
                              >
                                <img
                                  src={sectionData.image}
                                  alt="Hero"
                                  className="w-full h-full object-contain"
                                  style={{
                                    objectPosition: `${sectionData.imagePositionX || 50}% ${sectionData.imagePositionY || 50}%`
                                  }}
                                />
                              </div>
                            </div>
                          )}

                          {/* Настройки позиции и зума для Hero */}
                          <div className="space-y-3 p-3 bg-background rounded border border-border max-w-md">
                            <div>
                              <label className="text-xs text-muted-foreground mb-2 block">
                                Ширина (зум): {sectionData.imageZoom || 100}%
                              </label>
                              <Slider
                                value={[sectionData.imageZoom || 100]}
                                onValueChange={(value) => handleTextChange(activeSection, 'imageZoom', value[0])}
                                min={50}
                                max={200}
                                step={5}
                                className="w-full"
                              />
                            </div>
                            <div>
                              <label className="text-xs text-muted-foreground mb-2 block">
                                Высота: {sectionData.imageHeight || 100}%
                              </label>
                              <Slider
                                value={[sectionData.imageHeight || 100]}
                                onValueChange={(value) => handleTextChange(activeSection, 'imageHeight', value[0])}
                                min={50}
                                max={200}
                                step={5}
                                className="w-full"
                              />
                            </div>
                            <div>
                              <label className="text-xs text-muted-foreground mb-2 block">
                                Позиция по горизонтали: {sectionData.imagePositionX || 50}%
                              </label>
                              <Slider
                                value={[sectionData.imagePositionX || 50]}
                                onValueChange={(value) => handleTextChange(activeSection, 'imagePositionX', value[0])}
                                min={0}
                                max={100}
                                step={5}
                                className="w-full"
                              />
                            </div>
                            <div>
                              <label className="text-xs text-muted-foreground mb-2 block">
                                Позиция по вертикали: {sectionData.imagePositionY || 50}%
                              </label>
                              <Slider
                                value={[sectionData.imagePositionY || 50]}
                                onValueChange={(value) => handleTextChange(activeSection, 'imagePositionY', value[0])}
                                min={0}
                                max={100}
                                step={5}
                                className="w-full"
                              />
                            </div>
                          </div>

                          <label className="flex items-center justify-center gap-2 p-4 border-2 border-dashed border-border rounded cursor-pointer hover:bg-muted transition-colors max-w-md">
                            <Upload className="w-5 h-5" />
                            <span>{sectionData.image ? 'Изменить изображение' : 'Загрузить изображение'}</span>
                            <input
                              type="file"
                              accept="image/*"
                              onChange={(e) => handleImageUpload(e, activeSection, field)}
                              className="hidden"
                            />
                          </label>
                        </div>
                      ) : (
                        <div>
                          {value && (
                            <div className="mb-4">
                              <img
                                src={value}
                                alt={field}
                                className="max-w-full h-auto rounded max-h-64 object-cover"
                              />
                            </div>
                          )}
                          <label className="flex items-center justify-center gap-2 p-4 border-2 border-dashed border-border rounded cursor-pointer hover:bg-muted transition-colors">
                            <Upload className="w-5 h-5" />
                            <span>Загрузить изображение</span>
                            <input
                              type="file"
                              accept="image/*"
                              onChange={(e) =>
                                handleImageUpload(e, activeSection, field)
                              }
                              className="hidden"
                            />
                          </label>
                        </div>
                      )

                    /* Длинный текст */
                    ) : typeof value === "string" && value.length > 100 ? (
                      <div className="space-y-3">
                        <div>
                          <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-2">
                            🇷🇺 Русский
                          </label>
                          <Textarea
                            value={value}
                            onChange={(e) =>
                              handleTextChange(activeSection, field, e.target.value)
                            }
                            className="min-h-24"
                            placeholder={`Введите ${field} на русском`}
                          />
                        </div>
                        <div>
                          <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-2">
                            🇬🇧 English
                          </label>
                          <Textarea
                            value={''}
                            onChange={(e) => {
                              const newValue = { ru: value, en: e.target.value };
                              handleTextChange(activeSection, field, newValue);
                            }}
                            className="min-h-24"
                            placeholder={`Enter ${field} in English`}
                          />
                        </div>
                      </div>

                    /* Объект с переводами (уже сохранен) */
                    ) : typeof value === "object" && value !== null && (value.ru !== undefined || value.en !== undefined) ? (
                      <div className="space-y-3">
                        <div>
                          <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-2">
                            🇷🇺 Русский
                          </label>
                          <Textarea
                            value={value.ru || ''}
                            onChange={(e) => {
                              const newValue = { ...value, ru: e.target.value };
                              handleTextChange(activeSection, field, newValue);
                            }}
                            className="min-h-24"
                            placeholder={`Введите ${field} на русском`}
                          />
                        </div>
                        <div>
                          <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-2">
                            🇬🇧 English
                          </label>
                          <Textarea
                            value={value.en || ''}
                            onChange={(e) => {
                              const newValue = { ...value, en: e.target.value };
                              handleTextChange(activeSection, field, newValue);
                            }}
                            className="min-h-24"
                            placeholder={`Enter ${field} in English`}
                          />
                        </div>
                      </div>

                    /* Обычный текст */
                    ) : typeof value === "string" ? (
                      <div className="space-y-3">
                        <div>
                          <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-2">
                            🇷🇺 Русский
                          </label>
                          <Input
                            value={value}
                            onChange={(e) =>
                              handleTextChange(
                                activeSection,
                                field,
                                e.target.value
                              )
                            }
                            placeholder={`Введите ${field} на русском`}
                          />
                        </div>
                        <div>
                          <label className="text-xs text-muted-foreground mb-1 block flex items-center gap-2">
                            🇬🇧 English
                          </label>
                          <Input
                            value={''}
                            onChange={(e) => {
                              const newValue = { ru: value, en: e.target.value };
                              handleTextChange(activeSection, field, newValue);
                            }}
                            placeholder={`Enter ${field} in English`}
                          />
                        </div>
                      </div>

                    /* Простой массив строк */
                    ) : Array.isArray(value) && typeof value[0] === 'string' ? (
                      <div className="space-y-2">
                        {value.map((item, index) => (
                          <Input
                            key={index}
                            value={item}
                            onChange={(e) => {
                              const newArray = [...value];
                              newArray[index] = e.target.value;
                              handleTextChange(
                                activeSection,
                                field,
                                newArray
                              );
                            }}
                            placeholder={`${field} ${index + 1}`}
                          />
                        ))}
                      </div>

                    /* Остальное */
                    ) : (
                      <pre className="bg-background p-4 rounded overflow-auto text-xs">
                        {JSON.stringify(value, null, 2)}
                      </pre>
                    )}
                  </div>
                ))}
              </div>
            </div>

            {/* Save Button */}
            <div className="flex justify-end gap-4">
              <Button
                variant="outline"
                onClick={loadContent}
                disabled={saving}
              >
                Отмена
              </Button>
              <Button
                onClick={saveContent}
                disabled={saving}
              >
                <Save className="w-4 h-4 mr-2" />
                {saving ? "Сохранение..." : "Сохранить"}
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default AdminDashboard;
