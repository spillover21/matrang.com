import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Menu, X, Phone } from "lucide-react";
import { useContent } from "@/hooks/useContent";

const Header = () => {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const { content } = useContent();

  const scrollToSection = (id: string) => {
    const element = document.getElementById(id);
    if (element) {
      element.scrollIntoView({ behavior: "smooth" });
    }
    setIsMenuOpen(false);
  };

  return (
    <header className="fixed top-0 left-0 right-0 z-50 bg-background/95 backdrop-blur-sm border-b border-border">
      <div className="container mx-auto px-4">
        <div className="flex items-center justify-between h-16 md:h-20">
          {/* Logo */}
          <div className="flex items-center gap-3">
            {content.header?.logoImage ? (
              <img
                src={content.header.logoImage}
                alt={content.header.logoText || "logo"}
                className="h-12 w-12 rounded-sm object-contain drop-shadow-[0_0_14px_rgba(255,215,0,0.35)]"
              />
            ) : (
              <div className="w-12 h-12 bg-primary rounded-sm flex items-center justify-center drop-shadow-[0_0_14px_rgba(255,215,0,0.35)]">
                <span className="font-display text-2xl text-primary-foreground">P</span>
              </div>
            )}
            <div className="leading-tight">
              <span className="font-display text-2xl tracking-wider text-foreground block drop-shadow-[0_0_12px_rgba(255,215,0,0.35)]">
                {content.header?.logoText || "PITBULLELITE"}
              </span>
              {content.header?.tagline && (
                <span
                  className="block text-sm font-semibold uppercase tracking-[0.18em] text-amber-400 drop-shadow-[0_0_12px_rgba(255,215,0,0.35)]"
                  style={{ letterSpacing: "0.18em" }}
                >
                  {content.header.tagline}
                </span>
              )}
            </div>
          </div>

          {/* Desktop Navigation */}
          <nav className="hidden md:flex items-center gap-8">
            <button
              onClick={() => scrollToSection("about")}
              className="font-heading text-sm uppercase tracking-wider text-muted-foreground hover:text-primary transition-colors"
            >
              О породе
            </button>
            <button
              onClick={() => scrollToSection("gallery")}
              className="font-heading text-sm uppercase tracking-wider text-muted-foreground hover:text-primary transition-colors"
            >
              Галерея
            </button>
            <button
              onClick={() => scrollToSection("contact")}
              className="font-heading text-sm uppercase tracking-wider text-muted-foreground hover:text-primary transition-colors"
            >
              Контакты
            </button>
            <Button variant="hero" size="sm" onClick={() => scrollToSection("contact")}>
              <Phone className="w-4 h-4" />
              Связаться
            </Button>
          </nav>

          {/* Mobile Menu Button */}
          <button
            className="md:hidden text-foreground"
            onClick={() => setIsMenuOpen(!isMenuOpen)}
          >
            {isMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
          </button>
        </div>

        {/* Mobile Navigation */}
        {isMenuOpen && (
          <nav className="md:hidden py-4 border-t border-border animate-fade-in">
            <div className="flex flex-col gap-4">
              <button
                onClick={() => scrollToSection("about")}
                className="font-heading text-sm uppercase tracking-wider text-muted-foreground hover:text-primary transition-colors text-left"
              >
                О породе
              </button>
              <button
                onClick={() => scrollToSection("gallery")}
                className="font-heading text-sm uppercase tracking-wider text-muted-foreground hover:text-primary transition-colors text-left"
              >
                Галерея
              </button>
              <button
                onClick={() => scrollToSection("contact")}
                className="font-heading text-sm uppercase tracking-wider text-muted-foreground hover:text-primary transition-colors text-left"
              >
                Контакты
              </button>
              <Button variant="hero" size="sm" onClick={() => scrollToSection("contact")}>
                <Phone className="w-4 h-4" />
                Связаться
              </Button>
            </div>
          </nav>
        )}
      </div>
    </header>
  );
};

export default Header;
