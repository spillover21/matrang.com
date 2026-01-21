import { Shield, Heart, Zap, Award } from "lucide-react";
import { useContent } from "@/hooks/useContent";
import { useLanguage } from "@/hooks/useLanguage";

const iconMap = {
  Shield,
  Heart,
  Zap,
  Award
};

const AboutSection = () => {
  const { content, loading } = useContent();
  const { t } = useLanguage();

  if (loading || !content.about) {
    return null;
  }

  const { tag, title, description, features, stats } = content.about;

  return (
    <section id="about" className="py-24 bg-card">
      <div className="container mx-auto px-4">
        <div className="text-center mb-16">
          {tag && (
            <span className="inline-block font-heading text-sm uppercase tracking-[0.3em] text-primary mb-4">
              {t(tag)}
            </span>
          )}
          {title && (
            <h2 className="font-display text-5xl md:text-7xl mb-6" dangerouslySetInnerHTML={{ __html: t(title) }} />
          )}
          {description && (
            <p className="font-body text-lg text-muted-foreground max-w-2xl mx-auto">
              {t(description)}
            </p>
          )}
        </div>

        {features && features.length > 0 && (
          <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            {features.map((feature, index) => {
              const IconComponent = iconMap[feature.icon as keyof typeof iconMap] || Shield;
              return (
                <div
                  key={index}
                  className="group p-8 bg-secondary border border-border hover:border-primary/50 transition-all duration-300"
                >
                  <div className="w-14 h-14 bg-primary/10 flex items-center justify-center mb-6 group-hover:bg-primary/20 transition-colors">
                    <IconComponent className="w-7 h-7 text-primary" />
                  </div>
                  <h3 className="font-display text-2xl mb-3">{t(feature.title)}</h3>
                  <p className="font-body text-muted-foreground text-sm leading-relaxed whitespace-pre-line">
                    {t(feature.description)}
                  </p>
                </div>
              );
            })}
          </div>
        )}

        {/* Stats */}
        {stats && stats.length > 0 && (
          <div className="mt-20 grid grid-cols-2 md:grid-cols-4 gap-8">
            {stats.map((stat, index) => (
              <div key={index} className="text-center">
                <div className="font-display text-5xl md:text-6xl text-gradient-gold mb-2">
                  {t(stat.value)}
                </div>
                <div className="font-heading text-sm uppercase tracking-wider text-muted-foreground">
                  {t(stat.label)}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </section>
  );
};

export default AboutSection;
