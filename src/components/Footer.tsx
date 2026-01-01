import { useContent } from "@/hooks/useContent";

const Footer = () => {
  const { content, loading } = useContent();

  if (loading || !content.footer) {
    return null;
  }

  const { copyright, social = [] } = content.footer;

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
