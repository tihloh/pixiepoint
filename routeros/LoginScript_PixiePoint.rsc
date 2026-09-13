# PixiePoint RouterOS v7 HotSpot on-login script
# Legacy comment: duration,amount_pesos,is_extension,vendo_name
# Example: 1h,10,0,Main Vendo
# Replace the API key with the key assigned to this router in PixiePoint.

:local ppApiUrl "https://hs.portalx.win/api/router/login-event"
:local ppApiKey "REPLACE_WITH_THIS_ROUTER_API_KEY"
:local ppSchedulerPrefix "pp-exp-"

:local ppUser $user
:local ppAddress $address
:local ppMac $"mac-address"
:local ppInterface $interface
:local ppIdentity [/system identity get name]

# Only generated voucher characters are accepted before constructing an expiry script.
:if (!($ppUser~"^[A-Za-z0-9_-]+$")) do={
    :log warning ("PixiePoint: unsupported HotSpot username: " . $ppUser)
    :return
}

:local ppUserId [/ip hotspot user find where name=$ppUser]
:if ([:len $ppUserId] = 0) do={
    # RADIUS users are tracked by RADIUS accounting and need no local scheduler.
    :log info ("PixiePoint: RADIUS login observed for " . $ppUser)
    :return
}

:local ppComment [/ip hotspot user get $ppUserId comment]
:if ([:len $ppComment] = 0) do={ :return }

:local ppMeta [:toarray $ppComment]
:local ppEventKey ""
:local ppDuration 0s
:local ppAmount 0
:local ppExtension 0
:local ppVendo ""

:if (($ppMeta->0) = "pp-pending") do={
    :if ([:len $ppMeta] < 6) do={
        :log warning ("PixiePoint: invalid pending metadata for " . $ppUser)
        :return
    }
    :set ppEventKey ($ppMeta->1)
    :set ppDuration [:totime ($ppMeta->2)]
    :set ppAmount [:tonum ($ppMeta->3)]
    :set ppExtension [:tonum ($ppMeta->4)]
    :do { :set ppVendo [:convert ($ppMeta->5) from=base64] } on-error={
        :log warning ("PixiePoint: invalid pending vendo metadata for " . $ppUser)
        :return
    }
} else={
    :if ([:len $ppMeta] < 4) do={
        :log warning ("PixiePoint: invalid voucher metadata for " . $ppUser)
        :return
    }
    :set ppDuration [:totime ($ppMeta->0)]
    :set ppAmount [:tonum ($ppMeta->1)]
    :set ppExtension [:tonum ($ppMeta->2)]
    :set ppVendo ($ppMeta->3)
    :if ($ppDuration <= 0s) do={
        :log warning ("PixiePoint: voucher duration is missing for " . $ppUser)
        :return
    }

    :local ppSchedulerName ($ppSchedulerPrefix . $ppUser)
    :local ppSchedulerId [/system scheduler find where name=$ppSchedulerName]
    :if (($ppExtension = 1) and ([:len $ppSchedulerId] > 0)) do={
        :local ppOldInterval [/system scheduler get $ppSchedulerId interval]
        /system scheduler set $ppSchedulerId interval=($ppOldInterval + $ppDuration)
    } else={
        :if ([:len $ppSchedulerId] = 0) do={
            :local ppDate [/system clock get date]
            :local ppTime [/system clock get time]
            :local ppOnExpire (":local u \"" . $ppUser . "\";\r\n" . \
                "/ip hotspot active remove [find where user=\$u];\r\n" . \
                "/ip hotspot cookie remove [find where user=\$u];\r\n" . \
                "/ip hotspot user remove [find where name=\$u];\r\n" . \
                "/system scheduler remove [find where name=(\"" . $ppSchedulerPrefix . "\" . \$u)];")
            /system scheduler add name=$ppSchedulerName start-date=$ppDate start-time=$ppTime \
                interval=$ppDuration policy=read,write,test on-event=$ppOnExpire \
                comment=("PixiePoint expiry for " . $ppUser)
        }
    }

    # Persist retry state before the network request. A failed upload cannot apply
    # the extension twice on the next login.
    :local ppDate [/system clock get date]
    :local ppTime [/system clock get time]
    :set ppEventKey ($ppIdentity . "|" . $ppUser . "|" . $ppDate . "|" . $ppTime . "|" . $ppExtension)
    /ip hotspot user set $ppUserId comment=("pp-pending," . $ppEventKey . "," . \
        [:tostr $ppDuration] . "," . $ppAmount . "," . $ppExtension . "," . [:convert $ppVendo to=base64])
}

:local ppDeviceName ""
:local ppLease [/ip dhcp-server lease find where mac-address=$ppMac]
:if ([:len $ppLease] > 0) do={
    :do { :set ppDeviceName [/ip dhcp-server lease get $ppLease host-name] } on-error={}
}

:local ppPayload ({})
:set ($ppPayload->"event_key") $ppEventKey
:set ($ppPayload->"router_identity") $ppIdentity
:set ($ppPayload->"username") $ppUser
:set ($ppPayload->"mac") [:tostr $ppMac]
:set ($ppPayload->"client_ip") [:tostr $ppAddress]
:set ($ppPayload->"interface_name") [:tostr $ppInterface]
:set ($ppPayload->"device_name") $ppDeviceName
:set ($ppPayload->"vendo_name") $ppVendo
:set ($ppPayload->"amount_pesos") $ppAmount
:set ($ppPayload->"duration_seconds") ([:tonsec $ppDuration] / 1000000000)
:set ($ppPayload->"is_extension") $ppExtension
:local ppJson [:serialize to=json value=$ppPayload options=json.no-string-conversion]

:do {
    :local ppResult [/tool fetch url=$ppApiUrl http-method=post \
        http-header-field=("Content-Type:application/json,X-PixiePoint-Key:" . $ppApiKey) \
        http-data=$ppJson check-certificate=yes http-max-redirect-count=0 \
        idle-timeout=5s output=user as-value]
    :if (($ppResult->"status") = "finished") do={
        :local ppResponse [:deserialize from=json value=($ppResult->"data")]
        :if (($ppResponse->"ok") = true) do={
            /ip hotspot user set $ppUserId comment=""
            :log info ("PixiePoint: login event accepted for " . $ppUser)
            :return
        }
    }
    :log warning ("PixiePoint: server rejected login event for " . $ppUser . "; retry is pending")
} on-error={
    # Customer access remains successful; the pending comment retries next login.
    :log warning ("PixiePoint: login event upload failed for " . $ppUser . "; retry is pending")
}
