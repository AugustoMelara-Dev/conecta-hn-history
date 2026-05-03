export default function PublicImage({
    src,
    alt,
    className = '',
    width = 1200,
    height = 800,
    loading = 'lazy',
    decoding = 'async',
}) {
    if (!src) {
        return null;
    }

    return (
        <img
            src={src}
            alt={alt}
            width={width}
            height={height}
            loading={loading}
            decoding={decoding}
            className={className}
        />
    );
}
