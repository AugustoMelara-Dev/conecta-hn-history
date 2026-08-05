const fs = require("fs");
const pubPath = "C:/Projects/conecta-hn - copia/resources/views/layouts/public.blade.php";
let pubContent = fs.readFileSync(pubPath, "utf8");
const oldPubFonts = "    {{-- Fonts --}}\r\n    <link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\r\n    <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\r\n    <link href=\"https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap\" rel=\"stylesheet\">";
const newPubFonts = "    {{-- Fonts: preconnect + preload critical typefaces used by the design system --}}\r\n    <link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\r\n    <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\r\n    <link rel=\"preload\" as=\"style\" href=\"https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Epilogue:wght@400;500;600;700;800&display=swap\" />\r\n    <link href=\"https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap\" rel=\"stylesheet\">\r\n    <link href=\"https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Epilogue:wght@400;500;600;700;800&display=swap\" rel=\"stylesheet\">";
if (pubContent.indexOf(oldPubFonts) !== -1) {
    pubContent = pubContent.replace(oldPubFonts, newPubFonts);
    fs.writeFileSync(pubPath, pubContent, "utf8");
    console.log("Updated public.blade.php");
} else {
    console.log("Public fonts block not found");
}
