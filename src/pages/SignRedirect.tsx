
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

    const handleRedirect = (port: number) => {
        // Always redirect to the VPS IP, never the current hostname (Hostinger)
        const targetHost = "72.62.114.139"; 
        const protocol = "http:"; // Usually http for this VPS setup based on history
        const targetUrl = `${protocol}//${targetHost}:${port}/sign/${id}`;
        
        console.log(`Redirecting to: ${targetUrl}`);
        window.location.href = targetUrl;
    };

    return (
        <div className="flex flex-col items-center justify-center min-h-screen bg-gray-50 p-4">
            <Card className="w-full max-w-md shadow-lg">
                <CardHeader className="text-center">
                    <CardTitle className="text-xl text-blue-700">Вход в систему подписи</CardTitle>
                    <CardDescription>Access Signing System</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <p className="text-sm text-gray-600 text-center">
                        Мы исправили проблему с подключением. Выберите сервер ниже:
                        <br />
                        <span className="text-xs text-gray-400">Connection fix. Select server below:</span>
                    </p>

                    <Button 
                        onClick={() => handleRedirect(9000)} 
                        className="w-full bg-blue-600 hover:bg-blue-700"
                    >
                        Открыть документ (Основной - Порт 9000)
                    </Button>
                    
                    <div className="text-xs text-center text-gray-400 py-2">
                         Если кнопка выше выдает "404", попробуйте этот вариант:
                    </div>

                    <Button 
                        onClick={() => handleRedirect(3000)} 
                        variant="outline"
                        className="w-full"
                    >
                        Запасной вариант (Порт 3000)
                    </Button>

                    <div className="pt-4 border-t text-xs text-center text-gray-400">
                        Target IP: 72.62.114.139 | Token: {id}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
};

export default SignRedirect;
