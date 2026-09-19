<script>
    document.addEventListener("DOMContentLoaded", () => {
        // Fix for MapPicker invalidateSize in tabs
        const fixMap = () => {
            if (window.dispatchEvent) {
                window.dispatchEvent(new Event("leaflet.invalidateSize"));
                window.dispatchEvent(new Event("resize"));
            }
        };
        
        window.addEventListener("resize", fixMap);
        
        // Monitor tab changes to trigger map resize
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === "aria-selected" || 
                    mutation.target.classList?.contains("fi-tabs-item")) {
                    setTimeout(fixMap, 200);
                }
            });
        });
        
        document.querySelectorAll(".fi-tabs-item").forEach(tab => {
            observer.observe(tab, { attributes: true });
        });
    });
</script>
