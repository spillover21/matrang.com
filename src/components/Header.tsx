import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Menu, X, Phone } from "lucide-react";
import { useContent } from "@/hooks/useContent";

const Header = () => {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const { content, loading } = useContent();

  const headerContent = !loading ? content.header : null;
  const logoImage = headerContent?.logoImage;
  const logoText = headerContent?.logoText;
  const tagline = headerContent?.tagline;
  const favicon = headerContent?.favicon;

  useEffect(() => {
    if (!favicon) return;
    const existing = document.querySelector<HTMLLinkElement>('link[rel="icon"]') || document.createElement('link');
    existing.rel = 'icon';
    existing.href = favicon;
    document.head.appendChild(existing);
  }, [favicon]);

  const scrollToSection = (id: string) => {
    const element = document.getElementById(id);
    if (element) {
      element.scrollIntoView({ behavior: "smooth" });
    }
    setIsMenuOpen(false);
  };

  return (
    <header className="fixed top-0 left-0 right-0 z-50 bg-[linear-gradient(120deg,#191410_0%,#0f0c0a_60%,#0b0b0b_100%)] border-b border-[#2d2516] shadow-[0_10px_35px_rgba(0,0,0,0.55)]">
      <div className="absolute inset-x-0 bottom-0 h-[2px] bg-gradient-to-r from-transparent via-amber-400/70 to-transparent" aria-hidden />
      <div className="container mx-auto px-4">
        <div className="flex items-center justify-between h-16 md:h-20">
          {/* Logo */}
          <div className="relative flex items-center gap-3">
            <div className="pointer-events-none absolute -inset-4 bg-[radial-gradient(circle_at_left_center,rgba(255,215,0,0.26),transparent_65%)] blur-xl" aria-hidden />
            {logoImage ? (
              <img
                src={logoImage}
                alt={logoText || "logo"}
                className="h-14 w-14 rounded-sm object-contain drop-shadow-[0_0_18px_rgba(255,215,0,0.45)]"
              />
            ) : (
              <div className="w-14 h-14 rounded-sm bg-[#1a140f] border border-[#2d2516] shadow-inner" aria-hidden />
            )}
            <div className={`leading-[1.05] relative min-w-[150px] ${headerContent ? "opacity-100" : "opacity-0"}`}>
              <span className="font-display text-3xl tracking-wider text-foreground block drop-shadow-[0_0_14px_rgba(255,215,0,0.4)]">
                {logoText || ""}
              </span>
              {tagline && (
                <span
                  className="mt-[-2px] block text-xs font-semibold uppercase tracking-[0.18em] text-[#f1c94a] drop-shadow-[0_0_14px_rgba(255,215,0,0.45)]"
                  style={{ letterSpacing: "0.18em" }}
                >
                  {tagline}
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
