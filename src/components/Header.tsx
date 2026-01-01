import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Menu, X, Phone } from "lucide-react";

const Header = () => {
  const [isMenuOpen, setIsMenuOpen] = useState(false);

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
          <div className="flex items-center gap-2">
            <div className="w-10 h-10 bg-primary rounded-sm flex items-center justify-center">
              <span className="font-display text-xl text-primary-foreground">P</span>
            </div>
            <span className="font-display text-2xl tracking-wider text-foreground">
              PITBULL<span className="text-primary">ELITE</span>
            </span>
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
