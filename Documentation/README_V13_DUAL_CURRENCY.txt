VPR V13.0
USD: US$1.00 using the existing verified Integration ID/Key flow with callback/polling.
ZiG: ZiG 26.60 displayed and the supplied Paynow Payment Link opens in a popup.

Important: the supplied generic Payment Link does not provide VPR with a transaction-specific callback or poll URL.
For security VPR does not treat 'popup closed' as proof of payment and does not release a ZiG search merely from closure.
A ZiG-capable Paynow API/callback integration is required for automatic confirmed release.
