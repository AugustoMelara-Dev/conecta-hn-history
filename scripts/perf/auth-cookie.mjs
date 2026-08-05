const baseUrl = (process.env.PERF_BASE_URL ?? "http://localhost:8000").replace(/\/$/, "");
const response = await fetch(`${baseUrl}/login`, { redirect: "manual" });

if (!response.ok) {
    throw new Error(`Login boundary returned HTTP ${response.status}.`);
}

const cookies = response.headers.getSetCookie?.() ?? [];
const session = cookies.find((cookie) => {
    const name = cookie.slice(0, cookie.indexOf("="));
    return /session/i.test(name) && !/^XSRF-TOKEN$/i.test(name);
});

if (!session) {
    throw new Error("No application session cookie was emitted.");
}

for (const attribute of ["httponly", "samesite="]) {
    if (!session.toLowerCase().includes(attribute)) {
        throw new Error(`Session cookie is missing ${attribute}.`);
    }
}

if (baseUrl.startsWith("https://") && !session.toLowerCase().includes("secure")) {
    throw new Error("HTTPS session cookie is missing Secure.");
}

console.log("Authentication cookie baseline passed.");
