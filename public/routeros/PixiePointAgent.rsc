# PixiePoint Router Agent
# Installed by PixiePoint quick setup.
# Keep this agent small; PixiePoint owns the business logic.

:local url "https://hs.portalx.win/hotspot/health"

:do {
    :local fetchResult [/tool fetch url=$url mode=https output=user as-value]
    :local data ($fetchResult->"data")
} on-error={
    :log warning "PixiePoint fetch failed"
}
