import Header from "@/components/Header";
import Footer from "@/components/Footer";
import { useContent } from "@/hooks/useContent";
import { useLanguage } from "@/hooks/useLanguage";

const Rules = () => {
  const { content, loading } = useContent();
  const { t } = useLanguage();

  if (loading || !content.rules) {
    return (
        <div className="min-h-screen bg-background flex flex-col">
            <Header />
            <main className="flex-grow container mx-auto px-4 py-24 text-center">
                Loading...
            </main>
            <Footer />
        </div>
    );
  }

  const { title, content: rulesContent } = content.rules;

  return (
    <div className="min-h-screen bg-background flex flex-col">
      <Header />
      <main className="flex-grow pt-24 pb-12">
        <div className="container mx-auto px-4 max-w-4xl">
          <h1 className="text-3xl md:text-4xl font-display text-primary mb-8 text-center uppercase">
            {t(title)}
          </h1>
          <div 
            className="prose prose-invert prose-lg max-w-none text-muted-foreground"
            dangerouslySetInnerHTML={{ __html: t(rulesContent) }}
          />
        </div>
      </main>
      <Footer />
    </div>
  );
};

export default Rules;
