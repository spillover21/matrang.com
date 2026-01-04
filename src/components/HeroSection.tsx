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
  const titles = hero.titles || [{ text: hero.title || '', size: hero.titleSize || 80 }];
  return (
    <section className="relative min-h-screen overflow-hidden bg-background">
      {/* Фон с контролируемым позиционированием как в галерее (object-position) */}
      <div className="absolute inset-0 p-4 md:p-6 lg:p-10">
        <div className="relative w-full h-full overflow-hidden rounded-xl bg-background/40">
          <div
            className="w-full h-full"
            style={{
              transform: `scale(${(hero.imageZoom ?? 100) / 100}, ${(hero.imageHeight ?? 100) / 100})`,
              transformOrigin: `${hero.imagePositionX ?? 50}% ${hero.imagePositionY ?? 50}%`,
            }}
          >
            <img
              src={hero.image}
              alt={hero.title}
              className="w-full h-full object-contain drop-shadow-2xl"
              style={{
                objectPosition: `${hero.imagePositionX ?? 50}% ${hero.imagePositionY ?? 50}%`,
              }}
            />
          </div>
        </div>
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
            <div className="mb-6">
              {titles.map((titleItem: any, index: number) => (
                <h1
                  key={index}
                  className="font-display leading-none"
                  style={{ fontSize: `${titleItem.size || 80}px` }}
                >
                  {titleItem.text}
                </h1>
              ))}
            </div>
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
    </section>
  );
};

export default HeroSection;
