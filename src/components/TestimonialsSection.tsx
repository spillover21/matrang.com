import { useContent } from "@/hooks/useContent";
import { useLanguage } from "@/hooks/useLanguage";
import {
  Carousel,
  CarouselContent,
  CarouselItem,
  CarouselNext,
  CarouselPrevious,
} from "@/components/ui/carousel";
import { Card } from "@/components/ui/card";

const TestimonialsSection = () => {
  const { content, loading } = useContent();
  const { t } = useLanguage();

  if (loading || !content.testimonials) {
    return null;
  }

  const { tag, title, description, items } = content.testimonials;

  return (
    <section id="testimonials" className="py-20 bg-background">
      <div className="container mx-auto px-4">
        <div className="text-center mb-12">
          {tag && (
            <span className="inline-block px-4 py-1 bg-primary/10 text-primary rounded-full text-sm font-semibold mb-4">
              {t(tag)}
            </span>
          )}
          {title && (
            <h2 className="text-4xl md:text-5xl font-black mb-4 tracking-tight">
              {t(title)}
            </h2>
          )}
          {description && (
            <p className="text-muted-foreground max-w-2xl mx-auto">
              {t(description)}
            </p>
          )}
        </div>

        <div className="max-w-5xl mx-auto">
          <Carousel
            opts={{
              align: "start",
              loop: true,
            }}
            className="w-full"
          >
            <CarouselContent>
              {items?.map((testimonial: any, index: number) => (
                <CarouselItem key={index} className="md:basis-1/2 lg:basis-1/3">
                  <div className="p-1">
                    <Card className="overflow-hidden border-2 hover:border-primary/50 transition-colors">
                      {testimonial.image && (
                        <div className="w-full h-auto">
                          <img
                            src={testimonial.image}
                            alt={testimonial.title || `Отзыв ${index + 1}`}
                            className="w-full h-auto object-contain max-h-[800px]"
                            loading="lazy"
                          />
                        </div>
                      )}
                      {testimonial.title && (
                        <div className="p-4 bg-card">
                          <p className="font-semibold text-center">
                            {testimonial.title}
                          </p>
                        </div>
                      )}
                    </Card>
                  </div>
                </CarouselItem>
              ))}
            </CarouselContent>
            <CarouselPrevious className="left-0 -translate-x-12" />
            <CarouselNext className="right-0 translate-x-12" />
          </Carousel>
        </div>
      </div>
    </section>
  );
};

export default TestimonialsSection;
