
import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

const SignRedirect = () => {
    const { id } = useParams();
    const [triedPorts, setTriedPorts] = useState<number[]>([]);

    useEffect(() => {
        // Automatically try to redirect to the most likely port (9000 is standard for this project setup)
        // But since users reported 404, we give them options.
        // We will NOT auto-redirect immediately to avoid "flickering" 404s if 9000 is wrong.
    }, [id]);

    const handleRedirect = (pathPattern: string, port: number) => {
        // Use IP to avoid DNS issues
        const targetHost = "72.62.114.139"; 
        const protocol = "http:";
        
        // Replace :token placeholder with actual ID
        const finalPath = pathPattern.replace(':token', id || '');
        
        const targetUrl = `${protocol}//${targetHost}:${port}${finalPath}`;
        
        console.log(`Redirecting to: ${targetUrl}`);
        window.location.href = targetUrl;
    };

    return (
        <div className="flex flex-col items-center justify-center min-h-screen bg-gray-50 p-4">
            <Card className="w-full max-w-md shadow-lg border-2 border-yellow-400">
                <CardHeader className="text-center bg-yellow-50">
                    <CardTitle className="text-xl text-yellow-800">Диагностика ссылки</CardTitle>
                    <CardDescription>Link Diagnostics Mode</CardDescription>
                </CardHeader>
                <CardContent className="space-y-3 pt-4">
                    <p className="text-sm text-gray-600 text-center mb-4">
                        Сервер подписи найден (Порт 9000), но адрес документа неверен.
                        Попробуйте варианты ниже, чтобы найти правильный путь:
                    </p>

                    <Button 
                        onClick={() => handleRedirect('/sign/:token', 9000)} 
                        className="w-full bg-blue-600"
                    >
                        Вариант 1: /sign/TOKEN (Текущий)
                    </Button>

                    <Button 
                        onClick={() => handleRedirect('/d/:token', 9000)} 
                        className="w-full bg-green-600 hover:bg-green-700"
                    >
                        Вариант 2: /d/TOKEN (Стандарт Documenso)
                    </Button>
                    
                    <Button 
                        onClick={() => handleRedirect('/document/:token', 9000)} 
                        className="w-full bg-purple-600 hover:bg-purple-700"
                    >
                        Вариант 3: /document/TOKEN
                    </Button>

                    <div className="pt-2 pb-2 text-center text-xs font-bold text-gray-500 border-t border-b">
                        Другие порты (если 9000 не работает)
                    </div>

                    <Button 
                        onClick={() => handleRedirect('/sign/:token', 80)} 
                        variant="outline"
                        className="w-full"
                    >
                        Порт 80 (Веб-стандарт)
                    </Button>

                     <Button 
                        onClick={() => handleRedirect('/sign/:token', 3000)} 
                        variant="ghost"
                        className="w-full text-xs text-gray-400"
                    >
                        Порт 3000 (Резерв)
                    </Button>

                    <div className="pt-2 text-xs text-center text-gray-400">
                        Token: {id}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
};

export default SignRedirect;
