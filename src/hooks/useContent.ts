import { useState, useEffect } from "react";

interface ContentData {
  [key: string]: any;
}

export const useContent = () => {
  const [content, setContent] = useState<ContentData>({});
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const loadContent = async () => {
      try {
        const response = await fetch("/api/api.php?action=get");
        const data = await response.json();
        if (data.success) {
          setContent(data.data);
        }
      } catch (error) {
        console.error("Ошибка при загрузке контента:", error);
      } finally {
        setLoading(false);
      }
    };

    loadContent();
  }, []);

  return { content, loading };
};
