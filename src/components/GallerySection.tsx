import { useState } from "react";
import { Button } from "@/components/ui/button";
import { useContent } from "@/hooks/useContent";
import {
  Carousel,
  CarouselContent,
  CarouselItem,
  CarouselNext,
  CarouselPrevious,
} from "@/components/ui/carousel";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

const GallerySection = () => {
  const { content, loading } = useContent();
  const [activeDog, setActiveDog] = useState<any | null>(null);

  if (loading || !content.gallery) {
    return null;
  }

  const dogs = content.gallery.dogs || [];

  return (
    <section id="gallery" className="py-24 bg-background">
      <div className="container mx-auto px-4">
        <div className="text-center mb-16">
          <span className="inline-block font-heading text-sm uppercase tracking-[0.3em] text-primary mb-4">
            Наши питомцы
          </span>
          <h2 className="font-display text-5xl md:text-7xl mb-6">
            <span className="text-gradient-gold">ГАЛЕРЕЯ</span>
          </h2>
          <p className="font-body text-lg text-muted-foreground max-w-2xl mx-auto">
            Познакомьтесь с нашими питомцами. Каждый из них — результат тщательной селекции и заботливого воспитания.
          </p>
        </div>

        <Carousel
          opts={{ align: "start", loop: true }}
          className="w-full"
        >
          <CarouselContent className="pb-10">
            {dogs.map((dog) => (
              <CarouselItem key={dog.id} className="md:basis-1/2 lg:basis-1/3">
                <div className="p-2 h-full">
                  <div
                    className="group relative h-full overflow-hidden rounded-xl border border-border bg-card/80 shadow-lg transition-all duration-500 hover:border-primary/60 hover:shadow-[0_10px_35px_rgba(255,215,0,0.25)] cursor-pointer"
                    onClick={() => setActiveDog(dog)}
                  >
                    <div className="relative aspect-[4/5] overflow-hidden bg-gradient-to-b from-background to-background/80">
                      <div className="absolute inset-0 pointer-events-none bg-gradient-to-t from-background/85 via-background/10 to-transparent" />
                      <div className="w-full h-full flex items-center justify-center">
                        <div
                          className="w-full h-full"
                          style={{
                            transform: `scale(${(dog.imageZoom || 100) / 100}, ${(dog.imageHeight || 100) / 100})`,
                            transformOrigin: `${dog.imagePositionX || 50}% ${dog.imagePositionY || 50}%`,
                          }}
                        >
                          <img
                            src={dog.image}
                            alt={dog.name}
                            className="w-full h-full object-contain transition-transform duration-700 group-hover:scale-105 drop-shadow-[0_0_25px_rgba(255,215,0,0.35)]"
                            style={{
                              objectPosition: `${dog.imagePositionX || 50}% ${dog.imagePositionY || 50}%`,
                            }}
                          />
                        </div>
                      </div>
                      <div className="absolute top-4 right-4">
                        <span
                          className={`font-heading text-xs uppercase tracking-wider px-3 py-1 rounded ${
                            dog.available
                              ? "bg-primary text-primary-foreground"
                              : "bg-muted text-muted-foreground"
                          }`}
                        >
                          {dog.available ? "В продаже" : "Производитель"}
                        </span>
                      </div>
                    </div>

                    <div className="p-4 flex flex-col gap-2">
                      <h3 className="font-display text-3xl tracking-tight">{dog.name}</h3>
                      <div className="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                        {dog.age && <span>{dog.age}</span>}
                        {dog.color && (
                          <>
                            <span className="w-1 h-1 bg-primary rounded-full" />
                            <span>{dog.color}</span>
                          </>
                        )}
                      </div>
                      {dog.price && (
                        <span className="font-display text-2xl text-primary">{dog.price}</span>
                      )}
                    </div>
                  </div>
                </div>
              </CarouselItem>
            ))}
          </CarouselContent>
          <CarouselPrevious className="left-0 -translate-x-10" />
          <CarouselNext className="right-0 translate-x-10" />
        </Carousel>
      </div>

      <Dialog open={!!activeDog} onOpenChange={(open) => setActiveDog(open ? activeDog : null)}>
        <DialogContent className="max-w-5xl bg-background/95 border border-border">
          <DialogHeader>
            <DialogTitle className="font-display text-3xl leading-tight">
              {activeDog?.name}
            </DialogTitle>
            <DialogDescription className="text-muted-foreground">
              {activeDog?.price || ""}
            </DialogDescription>
          </DialogHeader>
          <div className="grid md:grid-cols-2 gap-6 items-start">
            <div className="relative w-full aspect-square rounded-lg overflow-hidden bg-card border border-border shadow-[0_15px_45px_rgba(0,0,0,0.35)]">
              {activeDog && (
                <div
                  className="w-full h-full"
                  style={{
                    transform: `scale(${(activeDog.imageZoom || 100) / 100}, ${(activeDog.imageHeight || 100) / 100})`,
                    transformOrigin: `${activeDog.imagePositionX || 50}% ${activeDog.imagePositionY || 50}%`,
                  }}
                >
                  <img
                    src={activeDog.image}
                    alt={activeDog.name}
                    className="w-full h-full object-contain drop-shadow-[0_0_30px_rgba(255,215,0,0.35)]"
                    style={{
                      objectPosition: `${activeDog.imagePositionX || 50}% ${activeDog.imagePositionY || 50}%`,
                    }}
                  />
                </div>
              )}
            </div>
            <div className="space-y-3">
              {activeDog?.age && (
                <p className="text-sm text-muted-foreground">Возраст: {activeDog.age}</p>
              )}
              {activeDog?.color && (
                <p className="text-sm text-muted-foreground">Окрас: {activeDog.color}</p>
              )}
              {activeDog?.price && (
                <p className="text-lg font-display text-primary">{activeDog.price}</p>
              )}
              <div className="pt-2">
                <Button variant="hero" size="lg" onClick={() => {
                  const el = document.getElementById("contact");
                  if (el) el.scrollIntoView({ behavior: "smooth" });
                }}>
                  Связаться
                </Button>
              </div>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </section>
  );
};

export default GallerySection;
