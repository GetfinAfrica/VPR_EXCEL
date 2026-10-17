VPR V14.2 — USD PAYNOW VERIFIED CERTIFICATE FLOW

USD FLOW
1. Customer enters vehicle details and selects USD.
2. VPR creates a unique server-side Paynow transaction/reference.
3. The Paynow payment button opens the Paynow browser payment page.
4. VPR polls the transaction-specific Paynow poll URL and also accepts the signed Paynow result callback.
5. VPR validates Paynow hashes, reference and expected amount.
6. ONLY a Paynow paid/success state releases the vehicle search.
7. VPR automatically generates and opens the report certificate.

IMPORTANT
The supplied generic Paynow Payment/Link URL is retained in config.php as VPR_USD_PAYMENT_LINK, but is deliberately NOT used to unlock certificates. A generic static Payment Link does not provide VPR with a transaction-specific poll URL/reference that can safely prove that the particular customer paid. Using popup closure or return-to-site as proof would allow unpaid certificates.

The visible customer experience remains button-to-Paynow-to-certificate, while verification stays server-side.

DOMAIN FIX
V14.2 also fixes Paynow return/result URLs on domain-portable installs by deriving the active HTTPS domain through vpr_public_base_url().

DEPLOYMENT
This is a full system package. Preserve the current live data/vpr.sqlite when upgrading an existing VPR installation.
