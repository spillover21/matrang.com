import { useContent } from "@/hooks/useContent";

const Footer = () => {
  const { content, loading } = useContent();

  if (loading || !content.footer) {
    return null;
  }

  const { copyright, social = [] } = content.footer;
  const header = content.header || {};

  return (
    <footer className="relative py-8 bg-[linear-gradient(120deg,#191410_0%,#0f0c0a_60%,#0b0b0b_100%)] border-t border-[#2d2516] shadow-[0_-10px_35px_rgba(0,0,0,0.45)]">
      <div className="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-amber-400/70 to-transparent" aria-hidden />
      <div className="container mx-auto px-4">
        <div className="flex flex-col md:flex-row items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            {header.logoImage ? (
              <img
                src={header.logoImage}
                alt={header.logoText || "logo"}
                className="h-10 w-10 rounded-sm object-contain drop-shadow-[0_0_14px_rgba(255,215,0,0.35)]"
              />
            ) : (
              <div className="w-10 h-10 bg-primary rounded-sm flex items-center justify-center drop-shadow-[0_0_14px_rgba(255,215,0,0.35)]">
                <span className="font-display text-xl text-primary-foreground">P</span>
              </div>
            )}
            <div className="leading-tight">
              <span className="font-display text-2xl tracking-wider text-foreground block drop-shadow-[0_0_12px_rgba(255,215,0,0.35)]">
                {header.logoText || "PITBULLELITE"}
              </span>
              {header.tagline && (
                <span className="text-xs font-semibold uppercase tracking-[0.18em] text-[#f1c94a] drop-shadow-[0_0_12px_rgba(255,215,0,0.35)]">
                  {header.tagline}
                </span>
              )}
            </div>
          </div>
          
          {copyright && (
            <p className="font-body text-sm text-muted-foreground text-center">
              {copyright}
            </p>
          )}
          
          <div className="flex items-center gap-6">
            {social.map((item: any, idx: number) => (
              <a
                key={idx}
                href={item.url || "#"}
                target="_blank"
                rel="noreferrer"
                className="font-heading text-xs uppercase tracking-wider text-muted-foreground hover:text-primary transition-colors"
              >
                {item.name}
              </a>
            ))}
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
