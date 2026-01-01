import { Button } from "@/components/ui/button";
import { Phone, Mail, MapPin, MessageCircle } from "lucide-react";

const ContactSection = () => {
  return (
    <section id="contact" className="py-24 bg-card">
      <div className="container mx-auto px-4">
        <div className="grid lg:grid-cols-2 gap-16">
          {/* Left Column */}
          <div>
            <span className="inline-block font-heading text-sm uppercase tracking-[0.3em] text-primary mb-4">
              Свяжитесь с нами
            </span>
            <h2 className="font-display text-5xl md:text-7xl mb-6">
              ГОТОВЫ <span className="text-gradient-gold">НАЧАТЬ?</span>
            </h2>
            <p className="font-body text-lg text-muted-foreground mb-12">
              Ответим на все вопросы о породе, поможем выбрать щенка и 
              организуем доставку в любой город России.
            </p>

            <div className="space-y-6">
              <a
                href="tel:+79001234567"
                className="flex items-center gap-4 group"
              >
                <div className="w-14 h-14 bg-primary/10 flex items-center justify-center group-hover:bg-primary/20 transition-colors">
                  <Phone className="w-6 h-6 text-primary" />
                </div>
                <div>
                  <div className="font-heading text-sm uppercase tracking-wider text-muted-foreground">
                    Телефон
                  </div>
                  <div className="font-display text-2xl group-hover:text-primary transition-colors">
                    +7 (900) 123-45-67
                  </div>
                </div>
              </a>

              <a
                href="mailto:info@pitbullelite.ru"
                className="flex items-center gap-4 group"
              >
                <div className="w-14 h-14 bg-primary/10 flex items-center justify-center group-hover:bg-primary/20 transition-colors">
                  <Mail className="w-6 h-6 text-primary" />
                </div>
                <div>
                  <div className="font-heading text-sm uppercase tracking-wider text-muted-foreground">
                    Email
                  </div>
                  <div className="font-display text-2xl group-hover:text-primary transition-colors">
                    info@pitbullelite.ru
                  </div>
                </div>
              </a>

              <div className="flex items-center gap-4">
                <div className="w-14 h-14 bg-primary/10 flex items-center justify-center">
                  <MapPin className="w-6 h-6 text-primary" />
                </div>
                <div>
                  <div className="font-heading text-sm uppercase tracking-wider text-muted-foreground">
                    Адрес
                  </div>
                  <div className="font-display text-2xl">
                    Москва, Россия
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Right Column - Contact Form */}
          <div className="bg-secondary border border-border p-8 md:p-12">
            <h3 className="font-display text-3xl mb-8">
              ОСТАВИТЬ ЗАЯВКУ
            </h3>
            <form className="space-y-6">
              <div>
                <label className="font-heading text-sm uppercase tracking-wider text-muted-foreground block mb-2">
                  Ваше имя
                </label>
                <input
                  type="text"
                  className="w-full bg-background border border-border px-4 py-3 font-body text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary transition-colors"
                  placeholder="Введите имя"
                />
              </div>
              <div>
                <label className="font-heading text-sm uppercase tracking-wider text-muted-foreground block mb-2">
                  Телефон
                </label>
                <input
                  type="tel"
                  className="w-full bg-background border border-border px-4 py-3 font-body text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary transition-colors"
                  placeholder="+7 (___) ___-__-__"
                />
              </div>
              <div>
                <label className="font-heading text-sm uppercase tracking-wider text-muted-foreground block mb-2">
                  Сообщение
                </label>
                <textarea
                  className="w-full bg-background border border-border px-4 py-3 font-body text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary transition-colors h-32 resize-none"
                  placeholder="Расскажите, какой щенок вас интересует..."
                />
              </div>
              <Button variant="hero" size="xl" className="w-full">
                <MessageCircle className="w-5 h-5" />
                Отправить заявку
              </Button>
            </form>
          </div>
        </div>
      </div>
    </section>
  );
};

export default ContactSection;
