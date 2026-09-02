# PixiePoint Router Agent
# Installed by PixiePoint quick setup.
# Keep this agent small; PixiePoint owns the business logic.

:do {
    /tool fetch url="https://hs.portalx.win/hotspot/health" mode=https output=none check-certificate=yes
} on-error={
    :log warning "PixiePoint agent fetch failed"
}
