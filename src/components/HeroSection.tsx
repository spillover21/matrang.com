import { Button } from "@/components/ui/button";
import { ChevronDown } from "lucide-react";
import { useContent } from "@/hooks/useContent";

const HeroSection = () => {
  const { content, loading } = useContent();

  const scrollToGallery = () => {
    const element = document.getElementById("gallery");
    if (element) {
      element.scrollIntoView({ behavior: "smooth" });
    }
  };

  const scrollToContact = () => {
    const element = document.getElementById("contact");
    if (element) {
      element.scrollIntoView({ behavior: "smooth" });
    }
  };

  if (loading || !content.hero) {
    return null;
  }

  const hero = content.hero;

  return (
    <section className="relative min-h-screen overflow-hidden bg-background">
      {/* Фон — размещаем фото без обрезки, с отступами по краям */}
      <div className="absolute inset-0 p-6 md:p-10 lg:p-16">
        <img
          src={hero.image}
          alt={hero.title}
          className="w-full h-full object-contain drop-shadow-2xl"
        />
      </div>
      <div className="absolute inset-0 bg-gradient-to-r from-background/95 via-background/80 to-background/30" />

      <div className="relative z-10 container mx-auto px-4 py-16 lg:py-24">
        <div className="max-w-4xl">
          <div className="animate-slide-up">
            {hero.tag && (
              <span className="inline-block font-heading text-sm uppercase tracking-[0.3em] text-primary mb-4">
                {hero.tag}
              </span>
            )}
            <h1
              className="font-display text-5xl md:text-7xl lg:text-8xl leading-none mb-6"
              style={{ whiteSpace: "pre-line" }}
            >
              {hero.title}
            </h1>
            {hero.subtitle && (
              <p className="font-body text-lg md:text-xl text-muted-foreground max-w-2xl mb-8">
                {hero.subtitle}
              </p>
            )}
            <div className="flex flex-col sm:flex-row gap-4">
              <Button variant="hero" size="xl" onClick={scrollToGallery}>
                Смотреть щенков
              </Button>
              <Button variant="outline" size="xl" onClick={scrollToContact}>
                Связаться с нами
              </Button>
            </div>
          </div>
        </div>
      </div>

      {/* Scroll Indicator */}
      <div className="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce">
        <ChevronDown className="w-8 h-8 text-primary" />
      </div>

      {/* Decorative Elements */}
      <div className="absolute top-1/4 right-10 w-32 h-32 border border-primary/20 rotate-45 hidden lg:block" />
      <div className="absolute bottom-1/4 right-20 w-20 h-20 border border-primary/30 rotate-12 hidden lg:block" />
    </section>
  );
};

export default HeroSection;
