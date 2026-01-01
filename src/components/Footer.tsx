const Footer = () => {
  return (
    <footer className="py-8 bg-background border-t border-border">
      <div className="container mx-auto px-4">
        <div className="flex flex-col md:flex-row items-center justify-between gap-4">
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 bg-primary rounded-sm flex items-center justify-center">
              <span className="font-display text-sm text-primary-foreground">P</span>
            </div>
            <span className="font-display text-xl tracking-wider text-foreground">
              PITBULL<span className="text-primary">ELITE</span>
            </span>
          </div>
          
          <p className="font-body text-sm text-muted-foreground text-center">
            © 2024 PitbullElite. Все права защищены.
          </p>
          
          <div className="flex items-center gap-6">
            <a
              href="#"
              className="font-heading text-xs uppercase tracking-wider text-muted-foreground hover:text-primary transition-colors"
            >
              Instagram
            </a>
            <a
              href="#"
              className="font-heading text-xs uppercase tracking-wider text-muted-foreground hover:text-primary transition-colors"
            >
              Telegram
            </a>
            <a
              href="#"
              className="font-heading text-xs uppercase tracking-wider text-muted-foreground hover:text-primary transition-colors"
            >
              WhatsApp
            </a>
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
