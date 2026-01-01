import { useState } from "react";
import { Button } from "@/components/ui/button";
import { useContent } from "@/hooks/useContent";

const GallerySection = () => {
  const { content, loading } = useContent();
  const [selectedDog, setSelectedDog] = useState<number | null>(null);
  
  if (loading || !content.gallery) {
    return null;
  }
  
  const dogs = content.gallery.dogs || [];

  const scrollToContact = () => {
    const element = document.getElementById("contact");
    if (element) {
      element.scrollIntoView({ behavior: "smooth" });
    }
  };

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
            Познакомьтесь с нашими питомцами. Каждый из них — результат 
            тщательной селекции и заботливого воспитания.
          </p>
        </div>

        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
          {dogs.map((dog) => (
            <div
              key={dog.id}
              className="group relative overflow-hidden bg-card border border-border hover:border-primary/50 transition-all duration-500"
              onMouseEnter={() => setSelectedDog(dog.id)}
              onMouseLeave={() => setSelectedDog(null)}
            >
              {/* Image */}
              <div className="relative aspect-square overflow-hidden flex items-center justify-center bg-background">
                <img
                  src={dog.image}
                  alt={dog.name}
                  className="max-h-full max-w-full object-contain transition-transform duration-700 group-hover:scale-105"
                  style={{
                    objectPosition: `${dog.imagePositionX || 50}% ${dog.imagePositionY || 50}%`
                  }}
                />
                <div className="absolute inset-0 bg-gradient-to-t from-background via-transparent to-transparent opacity-80" />
                
                {/* Status Badge */}
                <div className="absolute top-4 right-4">
                  <span
                    className={`font-heading text-xs uppercase tracking-wider px-3 py-1 ${
                      dog.available
                        ? "bg-primary text-primary-foreground"
                        : "bg-muted text-muted-foreground"
                    }`}
                  >
                    {dog.available ? "В продаже" : "Производитель"}
                  </span>
                </div>
              </div>

              {/* Info */}
              <div className="absolute bottom-0 left-0 right-0 p-6">
                <h3 className="font-display text-4xl mb-2">{dog.name}</h3>
                <div className="flex items-center gap-4 mb-4">
                  <span className="font-body text-sm text-muted-foreground">
                    {dog.age}
                  </span>
                  <span className="w-1 h-1 bg-primary rounded-full" />
                  <span className="font-body text-sm text-muted-foreground">
                    {dog.color}
                  </span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="font-display text-2xl text-primary">
                    {dog.price}
                  </span>
                  {dog.available && (
                    <Button
                      variant="hero"
                      size="sm"
                      className={`transition-all duration-300 ${
                        selectedDog === dog.id
                          ? "opacity-100 translate-y-0"
                          : "opacity-0 translate-y-4"
                      }`}
                      onClick={scrollToContact}
                    >
                      Забронировать
                    </Button>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
};

export default GallerySection;
