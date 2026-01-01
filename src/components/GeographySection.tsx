import { useContent } from "@/hooks/useContent";
import { MapPin, AlertCircle } from "lucide-react";
import { GoogleMap, LoadScript, Marker, InfoWindow } from "@react-google-maps/api";
import { useMemo, useState } from "react";

const GeographySection = () => {
  const { content, loading } = useContent();
  const [selectedLocation, setSelectedLocation] = useState<any>(null);
  const [mapError, setMapError] = useState(false);

  if (loading || !content.geography) {
    return null;
  }

  const { tag, title, description, locations } = content.geography;
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

  const mapCenter = useMemo(
    () => ({ lat: 54.526, lng: 37.6173 }),
    []
  );

  const mapContainerStyle = useMemo(
    () => ({ width: "100%", height: "100%" }),
    []
  );

  const mapOptions = useMemo(
    () => ({
      disableDefaultUI: false,
      zoomControl: true,
      streetViewControl: false,
      mapTypeControl: true,
      fullscreenControl: true,
      mapTypeId: "roadmap",
    }),
    []
  );

  return (
    <section id="geography" className="py-20 bg-muted/30">
      <div className="container mx-auto px-4">
        <div className="text-center mb-12">
          {tag && (
            <span className="inline-block px-4 py-1 bg-primary/10 text-primary rounded-full text-sm font-semibold mb-4">
              {tag}
            </span>
          )}
          {title && (
            <h2 className="text-4xl md:text-5xl font-black mb-4 tracking-tight">
              {title}
            </h2>
          )}
          {description && (
            <p className="text-muted-foreground max-w-2xl mx-auto">
              {description}
            </p>
          )}
        </div>

        <div className="max-w-6xl mx-auto">
          <div className="relative bg-card border-2 border-border rounded-xl overflow-hidden mb-8">
            <div className="aspect-[16/9] relative">
              {apiKey && !mapError ? (
                <LoadScript
                  googleMapsApiKey={apiKey}
                  onError={() => setMapError(true)}
                >
                  <GoogleMap
                    mapContainerStyle={mapContainerStyle}
                    center={mapCenter}
                    zoom={4}
                    options={mapOptions}
                  >
                    {locations?.map((location: any, index: number) => {
                      if (!location.lat || !location.lng) return null;
                      return (
                        <Marker
                          key={index}
                          position={{ lat: location.lat, lng: location.lng }}
                          onClick={() => setSelectedLocation(location)}
                        />
                      );
                    })}

                    {selectedLocation && (
                      <InfoWindow
                        position={{ lat: selectedLocation.lat, lng: selectedLocation.lng }}
                        onCloseClick={() => setSelectedLocation(null)}
                      >
                        <div className="p-2">
                          <h3 className="font-semibold text-foreground">{selectedLocation.city}</h3>
                          {selectedLocation.count && (
                            <p className="text-sm text-muted-foreground">
                              {selectedLocation.count} {selectedLocation.count === 1 ? "щенок" : "щенков"}
                            </p>
                          )}
                        </div>
                      </InfoWindow>
                    )}
                  </GoogleMap>
                </LoadScript>
              ) : (
                <div className="w-full h-full bg-muted flex items-center justify-center flex-col gap-4 p-8">
                  <AlertCircle className="w-12 h-12 text-destructive" />
                  <div className="text-center space-y-2">
                    <p className="font-semibold text-foreground">Не удалось загрузить карту</p>
                    <p className="text-sm text-muted-foreground">
                      Проверьте Google Maps API ключ и включённые API в Google Cloud Console
                    </p>
                  </div>
                </div>
              )}
            </div>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            {locations?.map((location: any, index: number) => (
              <div
                key={index}
                className="flex items-center gap-2 p-3 bg-card border border-border rounded-lg hover:border-primary/50 transition-colors"
              >
                <MapPin className="w-5 h-5 text-primary flex-shrink-0" />
                <div>
                  <p className="font-semibold text-sm">{location.city}</p>
                  {location.count && (
                    <p className="text-xs text-muted-foreground">
                      {location.count} {location.count === 1 ? "щенок" : "щенков"}
                    </p>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
};

export default GeographySection;
