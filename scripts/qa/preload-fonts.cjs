const fs = require("fs");

// Update app.blade.php
const appPath = "C:/Projects/conecta-hn - copia/resources/views/layouts/app.blade.php";
let appContent = fs.readFileSync(appPath, "utf8");
const oldAppFonts = "    {{-- Fonts: Inter (preconnect for performance) --}}\n    <link rel=\"preconnect\" href=\"https://fonts.bunny.net\">\n    <link href=\"https://fonts.bunny.net/css?family=inter:400,500,600,700,800\" rel=\"stylesheet\" />";
const newAppFonts = "    {{-- Fonts: preconnect + preload critical typefaces used by the design system --}}\n    <link rel=\"preconnect\" href=\"https://fonts.bunny.net\" crossorigin>\n    <link rel=\"preload\" as=\"style\" href=\"https://fonts.bunny.net/css?family=inter:400,500,600,700,800\" />\n    <link rel=\"preload\" as=\"style\" href=\"https://fonts.bunny.net/css?family=manrope:400,500,600,700&family=epilogue:400,500,600,700;800&display=swap\" />\n    <link href=\"https://fonts.bunny.net/css?family=inter:400,500,600,700,800\" rel=\"stylesheet\" />\n    <link href=\"https://fonts.bunny.net/css?family=manrope:400,500,600,700&family=epilogue:400,500,600,700;800&display=swap\" rel=\"stylesheet\" />";
if (appContent.indexOf(oldAppFonts) !== -1) {
    appContent = appContent.replace(oldAppFonts, newAppFonts);
    fs.writeFileSync(appPath, appContent, "utf8");
    console.log("Updated app.blade.php");
} else {
    console.log("App fonts block not found");
}

// Update public.blade.php
const pubPath = "C:/Projects/conecta-hn - copia/resources/views/layouts/public.blade.php";
let pubContent = fs.readFileSync(pubPath, "utf8");
const oldPubFonts = "    {{-- Fonts --}}\n    <link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n    <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n    <link href=\"https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap\" rel=\"stylesheet\">";
const newPubFonts = "    {{-- Fonts: preconnect + preload critical typefaces used by the design system --}}\n    <link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n    <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n    <link rel=\"preload\" as=\"style\" href=\"https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Epilogue:wght@400;500;600;700;800&display=swap\" />\n    <link href=\"https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap\" rel=\"stylesheet\">\n    <link href=\"https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Epilogue:wght@400;500;600;700;800&display=swap\" rel=\"stylesheet\">";
if (pubContent.indexOf(oldPubFonts) !== -1) {
    pubContent = pubContent.replace(oldPubFonts, newPubFonts);
    fs.writeFileSync(pubPath, pubContent, "utf8");
    console.log("Updated public.blade.php");
} else {
    console.log("Public fonts block not found");
}
