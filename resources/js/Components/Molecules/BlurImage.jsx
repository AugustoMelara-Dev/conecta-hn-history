import { useState, useEffect } from 'react';
import { Blurhash } from 'react-blurhash';
import { cn } from '@/lib/utils'; // Assuming cn utility exists, or I can use template literals

export default function BlurImage({ src, blurhash, alt, className, ...props }) {
    const [imageLoaded, setImageLoaded] = useState(false);

    useEffect(() => {
        const img = new Image();
        img.src = src;
        img.onload = () => setImageLoaded(true);
    }, [src]);

    return (
        <div className={cn("relative overflow-hidden", className)}>
            {/* Blurhash Placeholder */}
            {blurhash && (
                <div className={cn(
                    "absolute inset-0 z-10 transition-opacity duration-500",
                    imageLoaded ? "opacity-0" : "opacity-100"
                )}>
                    <Blurhash
                        hash={blurhash}
                        width="100%"
                        height="100%"
                        resolutionX={32}
                        resolutionY={32}
                        punch={1}
                    />
                </div>
            )}

            {/* Actual Image */}
            <img
                src={src}
                alt={alt}
                className={cn(
                    "w-full h-full object-cover transition-opacity duration-500 ease-in-out",
                    imageLoaded ? "opacity-100" : "opacity-0"
                )}
                onLoad={() => setImageLoaded(true)}
                {...props}
            />
        </div>
    );
}
