
import { useEffect } from "react";
import { useParams } from "react-router-dom";

const SignRedirect = () => {
    const { id } = useParams();

    useEffect(() => {
        // Logging for debug
        console.log("SignRedirect detected signature request:", id);

        // Logic to handle incorrect port 9000 -> 8080 correction?
        // If the user is here, it means the URL /sign/... pointed to the React App.
        // We suspect the actual signing service is on port 8080 (where the API is).
        
        const currentUrl = window.location.href;
        if (currentUrl.includes(":9000")) {
             const newUrl = currentUrl.replace(":9000", ":8080");
             window.location.href = newUrl;
        } else {
             // If we are not on port 9000, maybe we just need to redirect to the bridge?
             // But let's try 8080 anyway if it's likely the Documenso instance.
             const targetUrl = `${window.location.protocol}//${window.location.hostname}:8080/sign/${id}`;
             window.location.href = targetUrl;
        }

    }, [id]);

    return (
        <div className="flex flex-col items-center justify-center min-h-screen bg-gray-50">
            <div className="p-8 text-center bg-white rounded-lg shadow-md">
                <h2 className="text-2xl font-bold text-gray-800 mb-4">Redirecting to Signing Service...</h2>
                <p className="text-gray-600 mb-4">Please wait while we connect you to the secure document server.</p>
                <div className="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
                
                <div className="mt-6 text-sm text-gray-500">
                    If you are not redirected automatically, <a href={`http://${window.location.hostname}:8080/sign/${id}`} className="text-blue-600 underline">click here</a>.
                </div>
            </div>
        </div>
    );
};

export default SignRedirect;
