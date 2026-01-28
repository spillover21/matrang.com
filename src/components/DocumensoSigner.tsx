import React, { useEffect } from 'react';
import { Card } from "@/components/ui/card";

interface DocumensoSignerProps {
  signingUrl: string;
  onSigned?: () => void;
  className?: string;
}

const DocumensoSigner: React.FC<DocumensoSignerProps> = ({ 
  signingUrl, 
  onSigned,
  className 
}) => {
  useEffect(() => {
    // Обработчик сообщений от Documenso (postMessage)
    // Documenso отправляет события, когда статус документа меняется или подпись завершена
    const handleMessage = (event: MessageEvent) => {
      // Важно: проверяйте origin в реальном продакшене для безопасности!
      // if (event.origin !== "https://app.documenso.com") return;

      const { type, data } = event.data;

      console.log("Documenso Event:", type, data);

      // Проверяем тип события. Имена событий могут отличаться в зависимости от версии.
      // Обычно это 'DOCUMENSO_DOCUMENT_SIGNED' или 'DOCUMENSO_DOCUMENT_COMPLETED'
      if (type === 'DOCUMENSO_DOCUMENT_COMPLETED' || type === 'DOCUMENSO_DOCUMENT_SIGNED') {
        if (onSigned) {
          onSigned();
        }
      }
    };

    window.addEventListener('message', handleMessage);

    return () => {
      window.removeEventListener('message', handleMessage);
    };
  }, [onSigned]);

  if (!signingUrl) {
    return <div className="text-center p-4">Ссылка для подписи не найдена</div>;
  }

  return (
    <Card className={`overflow-hidden border border-gray-200 rounded-lg shadow-sm ${className}`}>
      <div className="w-full h-[800px] bg-slate-50 relative">
        <iframe
          src={signingUrl}
          className="w-full h-full border-0"
          title="Sign Document"
          allow="clipboard-write; clipboard-read" // Нужно для удобства пользователя
        />
        <div className="absolute top-0 right-0 p-2 text-xs text-gray-400 bg-white/80 rounded-bl">
          Secure Signing via Documenso
        </div>
      </div>
    </Card>
  );
};

export default DocumensoSigner;
