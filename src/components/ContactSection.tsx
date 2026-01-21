import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Phone, Mail, MapPin, MessageCircle, Instagram, Youtube } from "lucide-react";
import { useToast } from "@/hooks/use-toast";
import { useContent } from "@/hooks/useContent";
import { useLanguage } from "@/hooks/useLanguage";

const iconMap = {
  Phone,
  Mail,
  MapPin,
  MessageCircle,
  Instagram,
  Youtube
};
const ContactSection = () => {
  const { content, loading } = useContent();
  const { t } = useLanguage();
  const { toast } = useToast();
  const [form, setForm] = useState({ name: "", phone: "", message: "" });
  const [submitting, setSubmitting] = useState(false);

  if (loading || !content.contact) {
    return null;
  }

  const { tag, title, description, phone, email, address } = content.contact;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.name.trim() || !form.phone.trim() || !form.message.trim()) {
      toast({ title: "Заполните все поля", variant: "destructive" });
      return;
    }
    setSubmitting(true);
    try {
      const response = await fetch("/api/api.php?action=contact", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "contact", ...form }),
      });
      const data = await response.json();
      if (data.success) {
        toast({ title: "Заявка отправлена", description: "Мы свяжемся с вами в ближайшее время" });
        setForm({ name: "", phone: "", message: "" });
      } else {
        toast({ title: "Не удалось отправить", description: data.message || "Попробуйте позже", variant: "destructive" });
      }
    } catch (error) {
      toast({ title: "Ошибка сети", description: "Проверьте подключение и попробуйте снова", variant: "destructive" });
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <section id="contact" className="py-24 bg-card">
      <div className="container mx-auto px-4">
        <div className="grid lg:grid-cols-2 gap-16">
          {/* Left Column */}
          <div>
            {tag && (
              <span className="inline-block font-heading text-sm uppercase tracking-[0.3em] text-primary mb-4">
                {t(tag)}
              </span>
            )}
            {title && (
              <h2 className="font-display text-5xl md:text-7xl mb-6">
                {t(title).split(" ").map((word, idx) => (
                  idx === 1 ? (
                    <span key={idx} className="text-gradient-gold">{` ${word} `}</span>
                  ) : (
                    <span key={idx}>{`${idx === 0 ? "" : " "}${word}`}</span>
                  )
                ))}
              </h2>
            )}
            {description && (
              <p className="font-body text-lg text-muted-foreground mb-12">
                {t(description)}
              </p>
            )}

            <div className="space-y-6">
              {phone && (
                <a href={`tel:${phone.replace(/\s+/g, "")}`} className="flex items-center gap-4 group">
                  <div className="w-14 h-14 bg-primary/10 flex items-center justify-center group-hover:bg-primary/20 transition-colors">
                    <Phone className="w-6 h-6 text-primary" />
                  </div>
                  <div>
                    <div className="font-heading text-sm uppercase tracking-wider text-muted-foreground">
                      Телефон
                    </div>
                    <div className="font-display text-2xl group-hover:text-primary transition-colors">
                      {phone}
                    </div>
                  </div>
                </a>
              )}

              {email && (
                <a href={`mailto:${email}`} className="flex items-center gap-4 group">
                  <div className="w-14 h-14 bg-primary/10 flex items-center justify-center group-hover:bg-primary/20 transition-colors">
                    <Mail className="w-6 h-6 text-primary" />
                  </div>
                  <div>
                    <div className="font-heading text-sm uppercase tracking-wider text-muted-foreground">
                      Email
                    </div>
                    <div className="font-display text-2xl group-hover:text-primary transition-colors">
                      {email}
                    </div>
                  </div>
                </a>
              )}

              {address && (
                <div className="flex items-center gap-4">
                  <div className="w-14 h-14 bg-primary/10 flex items-center justify-center">
                    <MapPin className="w-6 h-6 text-primary" />
                  </div>
                  <div>
                    <div className="font-heading text-sm uppercase tracking-wider text-muted-foreground">
                      Адрес
                    </div>
                    <div className="font-display text-2xl">
                      {address}
                    </div>
                  </div>
                </div>
              )}

              {/* Social Links */}
              {content.contact.social && content.contact.social.length > 0 && (
                <div className="pt-6 mt-6 border-t border-border">
                  <div className="font-heading text-sm uppercase tracking-wider text-muted-foreground mb-4">
                    Социальные сети
                  </div>
                  <div className="space-y-4">
                    {content.contact.social.map((item: any, index: number) => {
                      const IconComponent = iconMap[item.icon as keyof typeof iconMap] || MessageCircle;
                      return (
                        <a
                          key={index}
                          href={item.link}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="flex items-center gap-4 group"
                        >
                          <div className="w-14 h-14 bg-primary/10 flex items-center justify-center group-hover:bg-primary/20 transition-colors">
                            <IconComponent className="w-6 h-6 text-primary" />
                          </div>
                          <div>
                            <div className="font-heading text-sm uppercase tracking-wider text-muted-foreground">
                              {t(item.label)}
                            </div>
                            <div className="font-display text-2xl group-hover:text-primary transition-colors">
                              {t(item.value)}
                            </div>
                          </div>
                        </a>
                      );
                    })}
                  </div>
                </div>
              )}
            </div>

          </div>

          {/* Right Column - Contact Form */}
          <div className="bg-secondary border border-border p-8 md:p-12">
            <h3 className="font-display text-3xl mb-8">
              {t({ ru: 'ОСТАВИТЬ ЗАЯВКУ', en: 'SUBMIT REQUEST' })}
            </h3>
            <form className="space-y-6" onSubmit={handleSubmit}>
              <div>
                <label className="font-heading text-sm uppercase tracking-wider text-muted-foreground block mb-2">
                  {t({ ru: 'Ваше имя', en: 'Your name' })}
                </label>
                <input
                  type="text"
                  className="w-full bg-background border border-border px-4 py-3 font-body text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary transition-colors"
                  placeholder={t({ ru: 'Введите имя', en: 'Enter your name' })}
                  value={form.name}
                  onChange={(e) => setForm((prev) => ({ ...prev, name: e.target.value }))}
                />
              </div>
              <div>
                <label className="font-heading text-sm uppercase tracking-wider text-muted-foreground block mb-2">
                  {t({ ru: 'Телефон', en: 'Phone' })}
                </label>
                <input
                  type="tel"
                  className="w-full bg-background border border-border px-4 py-3 font-body text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary transition-colors"
                  placeholder="+7 (___) ___-__-__"
                  value={form.phone}
                  onChange={(e) => setForm((prev) => ({ ...prev, phone: e.target.value }))}
                />
              </div>
              <div>
                <label className="font-heading text-sm uppercase tracking-wider text-muted-foreground block mb-2">
                  {t({ ru: 'Сообщение', en: 'Message' })}
                </label>
                <textarea
                  className="w-full bg-background border border-border px-4 py-3 font-body text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary transition-colors h-32 resize-none"
                  placeholder={t({ ru: 'Расскажите, какой щенок вас интересует...', en: 'Tell us which puppy you are interested in...' })}
                  value={form.message}
                  onChange={(e) => setForm((prev) => ({ ...prev, message: e.target.value }))}
                />
              </div>
              <Button type="submit" variant="hero" size="xl" className="w-full" disabled={submitting}>
                <MessageCircle className="w-5 h-5" />
                {submitting ? t({ ru: 'Отправляем...', en: 'Sending...' }) : t({ ru: 'Отправить заявку', en: 'Send Request' })}
              </Button>
            </form>
          </div>
        </div>
      </div>
    </section>
  );
};

export default ContactSection;
