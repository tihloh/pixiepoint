# PixiePoint Router Agent quick installer
# Downloads the current agent from the PixiePoint server, installs it,
# and creates one 5-second scheduler.

:local agentUrl "https://hs.portalx.win/routeros/PixiePointAgent.rsc"
:local agentFile "PixiePointAgent.rsc"
:local scriptName "pixiepoint-agent"
:local schedulerName "pixiepoint-agent"

:do {
    /tool fetch url=$agentUrl mode=https dst-path=$agentFile check-certificate=yes
    :local source [/file get [find name=$agentFile] contents]

    /system scheduler remove [find name=$schedulerName]
    /system script remove [find name=$scriptName]

    /system script add name=$scriptName source=$source policy=read,write,test
    /system scheduler add name=$schedulerName interval=5s start-time=startup on-event="/system script run pixiepoint-agent" policy=read,write,test

    /file remove [find name=$agentFile]
    :log info "PixiePoint agent installed"
} on-error={
    :log error "PixiePoint agent installation failed"
}
