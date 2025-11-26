module.exports = {
    darkMode: "class",
    content: [
        "./templates/**/*.{html,twig}",
        "./src/**/*.{html,twig,js}",
        "./assets/**/*.{js,jsx,ts,tsx}",
    ],
    theme: {
        extend: {
            colors: {
                "primary": "#695AB0",
            },
            keyframes: {
                "fade-in": {
                    "0%": { opacity: "0" },
                    "100%": { opacity: "1" },
                },
                "fade-out": {
                    "0%": { opacity: "1" },
                    "100%": { opacity: "0" },
                },
                "zoom-in": {
                    "0%": { transform: "scale(0.95)" },
                    "100%": { transform: "scale(1)" },
                },
                "zoom-out": {
                    "0%": { transform: "scale(1)" },
                    "100%": { transform: "scale(0.95)" },
                },
            },
            animation: {
                "fade-in": "fade-in 0.2s ease-out",
                "fade-out": "fade-out 0.2s ease-out",
                "zoom-in": "zoom-in 0.2s ease-out",
                "zoom-out": "zoom-out 0.2s ease-out",
            },
        },
    },
    plugins: [require("tailwindcss-animate")],
};
