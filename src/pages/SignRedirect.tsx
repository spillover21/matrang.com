
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
        const hostname = window.location.hostname;
        const protocol = window.location.protocol;
        const targetUrl = `${protocol}//${hostname}:${port}/sign/${id}`;
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
                        Пожалуйста, выберите сервер для подписания документа.
                        <br />
                        <span className="text-xs text-gray-400">Please select a server to sign the document.</span>
                    </p>

                    <Button 
                        onClick={() => handleRedirect(9000)} 
                        className="w-full bg-blue-600 hover:bg-blue-700"
                    >
                        Открыть документ (Сервер 1 - Порт 9000)
                    </Button>

                    <Button 
                        onClick={() => handleRedirect(3000)} 
                        className="w-full bg-slate-600 hover:bg-slate-700"
                    >
                        Открыть документ (Сервер 2 - Порт 3000)
                    </Button>
                    
                     <Button 
                        onClick={() => handleRedirect(8080)} 
                        variant="outline"
                        className="w-full"
                    >
                        Открыть документ (Сервер 3 - Порт 8080)
                    </Button>

                    <div className="pt-4 border-t text-xs text-center text-gray-400">
                        Token ID: {id}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
};

export default SignRedirect;
