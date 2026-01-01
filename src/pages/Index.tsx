import Header from "@/components/Header";
import HeroSection from "@/components/HeroSection";
import AboutSection from "@/components/AboutSection";
import GallerySection from "@/components/GallerySection";
import ContactSection from "@/components/ContactSection";
import Footer from "@/components/Footer";
import TestimonialsSection from "@/components/TestimonialsSection";
import GeographySection from "@/components/GeographySection";

const Index = () => {
  return (
    <div className="min-h-screen bg-background">
      <Header />
      <main>
        <HeroSection />
        <AboutSection />
        <GallerySection />
        <TestimonialsSection />
        <GeographySection />
        <ContactSection />
      </main>
      <Footer />
    </div>
  );
};

export default Index;
