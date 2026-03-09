\# Custom Rental - Tosin



\*\*Requires at least:\*\* WordPress 6.0  

\*\*Tested up to:\*\* WordPress 6.7  

\*\*Requires Plugins:\*\* WooCommerce  

\*\*Stable tag:\*\* 1.0.0  

\*\*License:\*\* GPLv2 or later  



WooCommerce rental booking system — Greater London delivery zone, per-day or per-week pricing, Nominatim address search (no API key required).



---



\## Description



Adds a custom \*\*Rental\*\* product type to WooCommerce that replaces the standard purchase flow with a multi-step booking form.



\### Features



\- \*\*Custom Rental product type\*\*  

&nbsp; Set a product to \*\*Rental\*\* and the standard \*\*Add to Cart\*\* button is automatically replaced with a booking workflow.



\- \*\*Flexible pricing modes\*\*

&nbsp; - \*\*Per Day\*\*

&nbsp; - \*\*Per Week\*\*

&nbsp;   - Days automatically round up to the next full week  

&nbsp;     - 7 days = charged as \*\*1 week\*\*  

&nbsp;     - 8 days = charged as \*\*2 weeks\*\*



\- \*\*Postcode validation\*\*

&nbsp; - Customers must enter a postcode

&nbsp; - Only \*\*Greater London postcodes\*\* are accepted

&nbsp; - Validation via \*\*postcodes.io\*\* with a local fallback



\- \*\*Flatpickr date picker\*\*

&nbsp; - Blocked dates supported

&nbsp; - Minimum and maximum rental duration enforced on the frontend



\- \*\*Live price breakdown\*\*

&nbsp; Displays:

&nbsp; - Rental charge

&nbsp; - Delivery fee

&nbsp; - Refundable security deposit

&nbsp; - Total cost



\- \*\*Order data storage\*\*

&nbsp; Booking information saved to WooCommerce order line items:

&nbsp; - Pickup date

&nbsp; - Return date

&nbsp; - Duration

&nbsp; - Postcode

&nbsp; - Price breakdown



\- \*\*Standard WooCommerce checkout\*\*

&nbsp; Payment is handled through the normal WooCommerce checkout process — no custom payment logic required.



\- \*\*Admin surcharge framework\*\*

&nbsp; Global settings available for:

&nbsp; - Out-of-hours delivery surcharge

&nbsp; - Weekend delivery surcharge



---



\## Installation



1\. Upload the `lendocare-rental` folder to `/wp-content/plugins/`

2\. Activate the plugin via \*\*WordPress Admin → Plugins → Installed Plugins\*\*

3\. Edit or create a product and change the \*\*Product Type\*\* to \*\*Rental\*\*

4\. Configure the \*\*Rental Settings\*\* tab.



---



\## Rental Settings (Per Product)



| Field | Description |

|------|-------------|

| Charge rental | Per Day or Per Week |

| Price per day / week | Base rental rate |

| Security deposit | Refundable deposit added at checkout |

| Standard delivery fee | Base delivery and collection charge |

| Minimum rental (days) | Prevents bookings shorter than allowed |

| Maximum rental (days) | Prevents bookings longer than allowed |

| Blocked dates | JSON array of unavailable dates |



---



\## Global Settings



Navigate to:



`WooCommerce → Settings → Products → LendoCare Rental`



Available configuration:



\- Out-of-hours surcharge (%)

\- Out-of-hours time window

\- Weekend surcharge (%)



---



\## Changelog



\### 1.0.0

\- Initial release

