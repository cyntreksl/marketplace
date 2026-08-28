export type MarketplaceDocumentSection = {
    id: string;
    title: string;
    paragraphs: string[];
    bullets?: string[];
};

export type MarketplaceDocument = {
    eyebrow: string;
    title: string;
    summary: string;
    sections: MarketplaceDocumentSection[];
};

export const marketplaceDocuments: Record<string, MarketplaceDocument> = {
    about: {
        eyebrow: 'About ProDeals.lk',
        title: 'A marketplace made for Sri Lanka',
        summary:
            'ProDeals.lk helps people discover products from independent sellers, compare their options, and shop with clear marketplace standards.',
        sections: [
            {
                id: 'what-we-do',
                title: 'What we do',
                paragraphs: [
                    'ProDeals.lk is an online marketplace operated by CYNTREK SOLUTIONS PVT LTD. We provide the technology that brings buyers and independent sellers together; the seller named on a listing remains responsible for the product and fulfilment of the sale.',
                    'Our goal is to make local ecommerce easier to understand through moderated listings, practical buyer information, transparent order records, and clear routes to support.',
                ],
            },
            {
                id: 'how-the-marketplace-works',
                title: 'How the marketplace works',
                paragraphs: [
                    'Sellers apply to join, publish product information, and fulfil orders placed through the marketplace. ProDeals.lk reviews seller applications and listings, facilitates checkout and order records, and supports the returns process described in our policies.',
                ],
                bullets: [
                    'Buy-now listings can be added to the cart and purchased through checkout.',
                    'Auction listings follow their own bidding and winner-payment process.',
                    'Each seller receives and fulfils their part of a multi-seller order independently.',
                    'Support is available every day for marketplace and order questions.',
                ],
            },
            {
                id: 'company-information',
                title: 'Company information',
                paragraphs: [
                    'ProDeals.lk is operated by CYNTREK SOLUTIONS PVT LTD, a company registered in England and Wales under company number 16330229. Our registered office is published on this page and in the footer for transparency.',
                ],
            },
        ],
    },
    contact: {
        eyebrow: 'Contact',
        title: 'Talk to the ProDeals.lk team',
        summary:
            'Get help with an account, order, return, seller application, privacy request, or marketplace concern.',
        sections: [
            {
                id: 'customer-support',
                title: 'Customer support',
                paragraphs: [
                    'Email support@prodeals.lk with your order number or the email address on your account. Please do not send passwords, one-time codes, full card details, or other unnecessary sensitive information.',
                    'Support hours are 09:00–18:00 Sri Lanka Standard Time (UTC+05:30), seven days a week. Messages received outside these hours are reviewed during the next support window.',
                ],
            },
            {
                id: 'returns-and-orders',
                title: 'Orders and returns',
                paragraphs: [
                    'Use your buyer workspace for order history and eligible return requests. If a seller rejects a request or you need help coordinating an approved return, contact support with the relevant order and return-request details.',
                ],
            },
            {
                id: 'privacy-requests',
                title: 'Privacy requests',
                paragraphs: [
                    'Privacy and personal-data requests may also be sent to support@prodeals.lk. We may need to verify your identity before disclosing, correcting, exporting, or deleting personal information.',
                ],
            },
            {
                id: 'registered-office',
                title: 'Registered office',
                paragraphs: [
                    'CYNTREK SOLUTIONS PVT LTD, Office 4170, 58 Peregrine Road, Hainault, Ilford, Essex, United Kingdom, IG6 3SZ. This is the company registered office and is not a customer returns address.',
                ],
            },
        ],
    },
    help: {
        eyebrow: 'Help centre',
        title: 'Clear answers for buying and selling',
        summary:
            'Start with the guide that matches what you are trying to do, then contact support if you still need a hand.',
        sections: [
            {
                id: 'buying-help',
                title: 'Buying help',
                paragraphs: [
                    'Browse approved listings, check the seller and product information, choose an available payment method, and follow each seller order from your buyer workspace.',
                ],
                bullets: [
                    'Read the Buying Guide before your first order.',
                    'Check the Shipping Policy for fulfilment expectations.',
                    'Use the Returns & Refunds Policy for eligibility and timing.',
                    'Review the FAQ for common account and checkout questions.',
                ],
            },
            {
                id: 'selling-help',
                title: 'Selling help',
                paragraphs: [
                    'Seller applicants must provide accurate business and payout information, accept marketplace terms, and wait for approval before publishing products for sale.',
                ],
                bullets: [
                    'Read the Selling Guide and Seller Policy.',
                    'Check the Prohibited Items Policy before creating a listing.',
                    'Keep stock, prices, condition, warranty, and delivery information current.',
                    'Respond promptly to orders and return requests.',
                ],
            },
            {
                id: 'safety',
                title: 'Account and payment safety',
                paragraphs: [
                    'Keep communication and payment within the marketplace wherever possible. ProDeals.lk will never ask for your password or two-factor authentication code by email.',
                ],
            },
        ],
    },
    faq: {
        eyebrow: 'Frequently asked questions',
        title: 'A quick answer may be all you need',
        summary:
            'Open a question below for practical information about accounts, orders, payments, shipping, returns, and selling.',
        sections: [
            {
                id: 'marketplace-role',
                title: 'Is ProDeals.lk the seller?',
                paragraphs: [
                    'Usually, no. ProDeals.lk operates the marketplace, while the independent seller identified on the listing sells and fulfils the product. We moderate marketplace activity and provide support, payment, order, and return tools.',
                ],
            },
            {
                id: 'payment-methods',
                title: 'Which payment methods are available?',
                paragraphs: [
                    'Checkout may offer card payment, bank transfer, or cash on delivery. Availability can depend on the order value, listing category, seller, and operational settings shown at checkout.',
                ],
            },
            {
                id: 'multi-seller-orders',
                title: 'Why is my order split by seller?',
                paragraphs: [
                    'A cart may contain products from several sellers. Each seller receives an independent fulfilment order, so products may be dispatched separately and arrive at different times.',
                ],
            },
            {
                id: 'delivery',
                title: 'How do I know an order was delivered?',
                paragraphs: [
                    'The seller updates the order after confirmed delivery. That timestamp starts the seven-calendar-day return window for eligible buy-now order items.',
                ],
            },
            {
                id: 'returns',
                title: 'How do I request a return?',
                paragraphs: [
                    'Open your buyer workspace, choose an eligible order item, and submit the reason, quantity, description, and any helpful images. The seller approves or rejects the request. Support coordinates approved physical returns offline.',
                ],
            },
            {
                id: 'refund-time',
                title: 'When will I receive a refund?',
                paragraphs: [
                    'Refund processing starts after support confirms that the approved return is ready. Card refunds are submitted to the payment provider; bank-transfer and cash-on-delivery refunds require manual completion. Bank or provider posting times may follow.',
                ],
            },
            {
                id: 'seller-application',
                title: 'How do I become a seller?',
                paragraphs: [
                    'Choose Become a seller, create or sign in to an account, submit the requested seller information, and wait for marketplace review. Approval is not automatic.',
                ],
            },
            {
                id: 'support',
                title: 'How can I contact support?',
                paragraphs: [
                    'Email support@prodeals.lk. The team is available 09:00–18:00 Sri Lanka Standard Time, seven days a week.',
                ],
            },
        ],
    },
    buying: {
        eyebrow: 'Buying guide',
        title: 'Shop with clarity and confidence',
        summary:
            'A practical guide to evaluating listings, paying safely, following delivery, and getting post-purchase help.',
        sections: [
            {
                id: 'before-you-buy',
                title: 'Before you buy',
                paragraphs: [
                    'Review the complete listing, including product condition, specifications, price, stock, seller name, location, warranty statement, and available images. Ask support if marketplace information appears misleading or unsafe.',
                ],
            },
            {
                id: 'checkout',
                title: 'Checkout and payment',
                paragraphs: [
                    'Use accurate recipient, address, and phone details. Confirm the final total and payment method before placing the order. Never send a seller your password, security code, or full card number.',
                ],
                bullets: [
                    'Card payments are handled through the configured payment provider.',
                    'Bank-transfer instructions and verification may require additional processing.',
                    'Cash on delivery may be limited by order value or category.',
                ],
            },
            {
                id: 'delivery-and-inspection',
                title: 'Delivery and inspection',
                paragraphs: [
                    'Check the package and product promptly after delivery. Keep packaging and evidence if the item is damaged, incorrect, unsafe, incomplete, or materially different from the listing.',
                ],
            },
            {
                id: 'returns-and-support',
                title: 'Returns and support',
                paragraphs: [
                    'Eligible buy-now items may be requested for return within seven calendar days of seller-confirmed delivery. Submit the request from your buyer workspace and contact support if you need help coordinating an approved return.',
                ],
            },
        ],
    },
    selling: {
        eyebrow: 'Selling guide',
        title: 'Build a dependable marketplace presence',
        summary:
            'Understand seller onboarding, accurate listing standards, fulfilment, returns, and customer-data responsibilities.',
        sections: [
            {
                id: 'join',
                title: 'Apply to sell',
                paragraphs: [
                    'Create an account, submit accurate seller, contact, bank, pickup, and return information, and provide any requested verification documents. ProDeals.lk may approve, request changes, suspend, or reject a seller application.',
                ],
            },
            {
                id: 'list-accurately',
                title: 'Create accurate listings',
                paragraphs: [
                    'Use truthful titles, original or authorized images, precise condition and warranty statements, accurate stock, and a category that fits the product. Do not manipulate search, impersonate a brand, or list prohibited goods.',
                ],
            },
            {
                id: 'fulfil',
                title: 'Fulfil orders responsibly',
                paragraphs: [
                    'Prepare paid orders promptly, protect products in transit, supply useful courier and tracking information, and mark delivery only after it has genuinely been confirmed.',
                ],
            },
            {
                id: 'returns',
                title: 'Handle returns fairly',
                paragraphs: [
                    'Review buyer requests promptly and provide a clear reason when approving or rejecting. Sellers pay return shipping for approved requests. Support coordinates the physical return, and the marketplace processes the resulting refund.',
                ],
            },
            {
                id: 'buyer-data',
                title: 'Protect buyer data',
                paragraphs: [
                    'Use recipient, address, phone, and order information only to fulfil the transaction, meet legal obligations, and resolve support issues. Do not add buyers to marketing lists or disclose their information for unrelated purposes.',
                ],
            },
        ],
    },
    shipping: {
        eyebrow: 'Shipping policy',
        title: 'How marketplace orders are fulfilled',
        summary:
            'Independent sellers prepare and dispatch their own fulfilment orders, while ProDeals.lk provides order records and support.',
        sections: [
            {
                id: 'order-splitting',
                title: 'Orders may ship separately',
                paragraphs: [
                    'When a cart contains products from more than one seller, the marketplace creates a separate seller order for each seller. Dispatch dates, couriers, tracking references, and arrival times can therefore differ.',
                ],
            },
            {
                id: 'addresses-and-charges',
                title: 'Addresses and charges',
                paragraphs: [
                    'Buyers are responsible for providing a complete, reachable delivery address and phone number. Any applicable shipping charge must be shown as part of the checkout total or otherwise agreed through an authorized support process before payment.',
                ],
            },
            {
                id: 'tracking-and-delivery',
                title: 'Tracking and delivery',
                paragraphs: [
                    'Sellers provide available courier and tracking information. A seller marks the order delivered only after delivery has been confirmed. That timestamp controls return eligibility, so inaccurate delivery updates may lead to enforcement action.',
                ],
            },
            {
                id: 'delays-and-damage',
                title: 'Delays, missing parcels, and transit damage',
                paragraphs: [
                    'Contact support with the order number if tracking stalls, a parcel appears lost, or the delivered package is damaged. Keep the package, labels, and photographs where possible while the seller and courier review the issue.',
                ],
            },
        ],
    },
    returns: {
        eyebrow: 'Returns & refunds policy',
        title: 'A clear seven-day return process',
        summary:
            'Eligible buy-now order items can be requested for return within seven calendar days after seller-confirmed delivery.',
        sections: [
            {
                id: 'eligibility',
                title: 'Return eligibility',
                paragraphs: [
                    'The return request must be submitted no later than the exact delivery timestamp plus seven calendar days. The buyer may request up to the quantity purchased, less quantities already included in earlier requests.',
                    'Auction items are not covered by this workflow until an auction win has been converted into a normal marketplace order item.',
                ],
            },
            {
                id: 'reasons',
                title: 'Reasons for a return',
                paragraphs: [
                    'Available reasons include damaged item, incorrect item, not as described, missing parts, safety or authenticity concern, change of mind, and other. Products must be returned with included parts and packaging where reasonably possible.',
                ],
            },
            {
                id: 'request-and-decision',
                title: 'Request and seller decision',
                paragraphs: [
                    'Submit the request from the buyer workspace with a description and any useful photographs. The seller reviews the request and provides a reason when approving or rejecting it. The seller decision is final within the portal; a buyer may still contact support about a marketplace or legal concern.',
                ],
            },
            {
                id: 'return-shipping',
                title: 'Return shipping',
                paragraphs: [
                    'The seller pays return shipping for every approved request. Support coordinates the practical return instructions offline. Buyers should not send an item to the company registered office or another address that has not been confirmed for that return.',
                ],
            },
            {
                id: 'refunds',
                title: 'Refund processing',
                paragraphs: [
                    'The refund equals the approved quantity multiplied by the purchased unit price. Card refunds are submitted to the original payment provider. Bank-transfer and cash-on-delivery refunds are completed manually and recorded with a reference. Provider or bank posting times are outside the marketplace’s direct control.',
                ],
            },
            {
                id: 'limitations',
                title: 'Limitations',
                paragraphs: [
                    'A return may be rejected where the request is late, exceeds the purchased quantity, concerns damage caused after delivery, or cannot reasonably be matched to the order. Nothing in this policy excludes rights that cannot lawfully be excluded under applicable Sri Lankan consumer law.',
                ],
            },
        ],
    },
    terms: {
        eyebrow: 'Legal',
        title: 'Terms and conditions',
        summary:
            'These terms govern access to ProDeals.lk and set the responsibilities of buyers, sellers, and the marketplace operator.',
        sections: [
            {
                id: 'operator-and-acceptance',
                title: 'Operator and acceptance',
                paragraphs: [
                    'ProDeals.lk is operated by CYNTREK SOLUTIONS PVT LTD. By accessing the service, creating an account, placing an order, bidding, or applying to sell, you agree to these terms and the policies linked from them.',
                    'You must be legally able to enter the relevant transaction and must provide accurate, current information. A parent or guardian is responsible for use by a minor where permitted by law.',
                ],
            },
            {
                id: 'marketplace-role',
                title: 'Marketplace role',
                paragraphs: [
                    'ProDeals.lk provides marketplace, moderation, checkout, order-record, support, and return tools. Unless expressly identified otherwise, the sales contract is between the buyer and the independent seller shown on the listing.',
                    'Moderation reduces risk but is not a guarantee of a seller, product, description, availability, suitability, legality, or uninterrupted fulfilment.',
                ],
            },
            {
                id: 'accounts',
                title: 'Accounts and security',
                paragraphs: [
                    'You are responsible for protecting your credentials, passkeys, recovery codes, and account access. Tell support promptly if you suspect unauthorized use. We may restrict or suspend accounts to protect users, investigate misuse, comply with law, or enforce marketplace policies.',
                ],
            },
            {
                id: 'listings-orders-and-auctions',
                title: 'Listings, orders, and auctions',
                paragraphs: [
                    'Prices and availability may change before an order is accepted. Orders can be split by seller. Auction bids are binding subject to the auction rules, reserve, timing, payment requirements, and lawful cancellation rights shown by the service.',
                ],
            },
            {
                id: 'payments',
                title: 'Payments',
                paragraphs: [
                    'Available methods may include card, bank transfer, and cash on delivery. Payment providers and banks may impose their own terms. Do not bypass the platform or submit fraudulent proof of payment.',
                ],
            },
            {
                id: 'returns-and-disputes',
                title: 'Returns and support',
                paragraphs: [
                    'Returns and refunds follow the published Returns & Refunds Policy. Seller approve/reject decisions are final in the portal. Support may assist with marketplace-policy, safety, or legal concerns but does not promise a different commercial outcome.',
                ],
            },
            {
                id: 'acceptable-use',
                title: 'Acceptable use',
                paragraphs: [
                    'Do not misuse the service, interfere with security, scrape or overload systems without permission, manipulate reviews or bids, infringe rights, evade enforcement, or use the marketplace for unlawful, deceptive, or prohibited activity.',
                ],
            },
            {
                id: 'intellectual-property',
                title: 'Intellectual property',
                paragraphs: [
                    'The ProDeals.lk service, brand, software, and original content are protected by applicable rights. Sellers retain responsibility for having permission to use listing content and grant the operator a non-exclusive licence to host, reproduce, format, and display it for marketplace operation and promotion.',
                ],
            },
            {
                id: 'liability',
                title: 'Availability and liability',
                paragraphs: [
                    'The service may be changed, interrupted, or withdrawn for maintenance, security, legal, or operational reasons. To the extent permitted by law, the operator is not liable for indirect or consequential losses or for a seller’s independent acts. Nothing excludes liability or consumer rights that cannot lawfully be limited.',
                ],
            },
            {
                id: 'law',
                title: 'Governing law',
                paragraphs: [
                    'These terms are governed by the laws of Sri Lanka, and disputes are subject to the courts and lawful dispute-resolution authorities of Sri Lanka, while preserving mandatory rights that apply to a user or to the UK-incorporated operator.',
                ],
            },
            {
                id: 'contact',
                title: 'Contact',
                paragraphs: [
                    'Questions about these terms may be sent to support@prodeals.lk. Company and registered-office details appear in the footer and Contact page.',
                ],
            },
        ],
    },
    privacy: {
        eyebrow: 'Legal',
        title: 'Privacy notice',
        summary:
            'This notice explains how CYNTREK SOLUTIONS PVT LTD handles personal data when operating ProDeals.lk.',
        sections: [
            {
                id: 'controller',
                title: 'Who controls your data',
                paragraphs: [
                    'CYNTREK SOLUTIONS PVT LTD is the operator and primary controller for marketplace account, platform, support, and transaction records. Independent sellers may separately control personal data they lawfully need for fulfilment and their legal obligations.',
                ],
            },
            {
                id: 'data-collected',
                title: 'Data we collect',
                paragraphs: [
                    'Depending on how you use the service, we collect account and identity details, seller onboarding information and documents, contact details, delivery addresses, order and return records, payment method and provider references, listing content, support messages, device and security data, and uploaded return evidence.',
                    'Card details are submitted to the payment provider; ProDeals.lk should not receive or store a complete card number or security code.',
                ],
            },
            {
                id: 'purposes-and-bases',
                title: 'Why we use personal data',
                paragraphs: [
                    'We use data to provide accounts and marketplace services, process orders and returns, prevent fraud, protect security, communicate with users, moderate sellers and listings, meet accounting and legal obligations, resolve complaints, and improve service reliability.',
                    'Depending on the activity and applicable law, processing is based on contract, legal obligation, legitimate interests, consent, or another lawful basis. Where consent applies, it may be withdrawn without affecting earlier lawful processing.',
                ],
            },
            {
                id: 'sharing',
                title: 'Who receives data',
                paragraphs: [
                    'We share only what is reasonably required with the seller fulfilling an order, couriers, payment and banking providers, hosting and email providers, professional advisers, fraud and security services, and public authorities where lawfully required.',
                    'Sellers may use buyer delivery information only for fulfilment, returns, support, and legal obligations—not unrelated marketing.',
                ],
            },
            {
                id: 'international-transfers',
                title: 'International transfers',
                paragraphs: [
                    'The operator is incorporated in the United Kingdom and the marketplace targets users in Sri Lanka. Data may therefore be processed in or transferred between Sri Lanka, the United Kingdom, and locations used by service providers. We use legally required safeguards and assess transfer requirements where applicable.',
                ],
            },
            {
                id: 'retention',
                title: 'How long data is kept',
                paragraphs: [
                    'We keep data only as long as reasonably required for the purpose collected, account operation, transaction evidence, fraud prevention, dispute handling, tax and accounting obligations, legal claims, and regulatory requirements. Retention can differ by record type, and secure deletion or anonymisation follows when continued identification is no longer required.',
                ],
            },
            {
                id: 'rights',
                title: 'Your rights',
                paragraphs: [
                    'Subject to applicable law, you may ask for access, correction, deletion, restriction, objection, portability, information about processing, or withdrawal of consent. Some requests may be limited where records must be retained or another lawful exception applies.',
                    'Send a request to support@prodeals.lk. We may verify identity before acting. You may also complain to the competent data-protection or consumer authority.',
                ],
            },
            {
                id: 'security',
                title: 'Security',
                paragraphs: [
                    'We use organizational and technical safeguards appropriate to the service, including access controls, password hashing, optional stronger authentication, authorization checks, and protected evidence storage. No online service can promise absolute security.',
                ],
            },
            {
                id: 'changes',
                title: 'Changes to this notice',
                paragraphs: [
                    'We may update this notice when the service, providers, or legal requirements change. Material changes will be brought to users’ attention through an appropriate channel.',
                ],
            },
        ],
    },
    cookies: {
        eyebrow: 'Legal',
        title: 'Cookie policy',
        summary:
            'ProDeals.lk currently uses cookies and similar browser storage for security, sessions, and user-requested appearance preferences—not advertising or analytics.',
        sections: [
            {
                id: 'what-they-are',
                title: 'What cookies are',
                paragraphs: [
                    'Cookies are small values stored by a browser and returned to a website. Similar local-storage technology can remember a preference on the same device.',
                ],
            },
            {
                id: 'cookies-we-use',
                title: 'What ProDeals.lk uses',
                paragraphs: [
                    'The marketplace uses a session cookie to keep signed-in activity connected, CSRF/XSRF protection to defend form submissions, and an appearance preference to remember light, dark, or system mode. Authentication and security features may use related short-lived browser storage as required.',
                ],
                bullets: [
                    'Session and authentication: required to sign in and use protected marketplace areas.',
                    'Security and CSRF protection: required to validate trusted requests.',
                    'Appearance preference: remembers a display choice requested by the user.',
                ],
            },
            {
                id: 'no-tracking',
                title: 'No advertising or analytics cookies',
                paragraphs: [
                    'The current service does not intentionally set advertising, cross-site tracking, or analytics cookies. Because there are no non-essential tracking categories to accept or reject, we do not display a consent banner that offers a meaningless choice.',
                ],
            },
            {
                id: 'future-changes',
                title: 'Future changes',
                paragraphs: [
                    'If non-essential cookies or tracking scripts are introduced, this policy will be updated and a genuine consent mechanism will be added before those technologies run where consent is required.',
                ],
            },
            {
                id: 'browser-controls',
                title: 'Browser controls',
                paragraphs: [
                    'You can clear or block cookies in browser settings. Blocking required cookies can prevent sign-in, checkout, security protection, or preference storage from working correctly.',
                ],
            },
        ],
    },
    sellers: {
        eyebrow: 'Marketplace policy',
        title: 'Seller policy',
        summary:
            'These standards apply to every seller application, listing, order, buyer interaction, and return handled through ProDeals.lk.',
        sections: [
            {
                id: 'eligibility',
                title: 'Seller eligibility and verification',
                paragraphs: [
                    'Provide truthful identity, contact, store, bank, pickup, return, and document information. Keep it current. Approval is discretionary and may be suspended or withdrawn after risk, compliance, or performance review.',
                ],
            },
            {
                id: 'listings',
                title: 'Listing standards',
                paragraphs: [
                    'Listings must accurately identify the product, brand, condition, price, stock, location, specifications, and warranty. Images and text must be owned, authorized, or otherwise lawful. Prohibited items, counterfeit goods, keyword manipulation, duplicate spam, and deceptive pricing are not allowed.',
                ],
            },
            {
                id: 'orders',
                title: 'Orders and fulfilment',
                paragraphs: [
                    'Honour accepted orders, package goods appropriately, provide useful dispatch details, and mark delivery only after confirmation. Contact support promptly about stock or fulfilment failures instead of submitting misleading status updates.',
                ],
            },
            {
                id: 'returns',
                title: 'Returns and refunds',
                paragraphs: [
                    'Review return requests fairly and give a clear reason for each decision. Sellers pay return shipping on approved returns and cooperate with support’s offline instructions. Refunded quantities and amounts are recorded against the order and payment.',
                ],
            },
            {
                id: 'buyer-data',
                title: 'Buyer information',
                paragraphs: [
                    'Use personal information only for fulfilment, returns, support, safety, fraud prevention, and legal obligations. Protect it from unauthorized access and do not use it for unrelated marketing, sale, enrichment, or disclosure.',
                ],
            },
            {
                id: 'enforcement',
                title: 'Enforcement',
                paragraphs: [
                    'ProDeals.lk may return listings for correction, remove content, hold or cancel activity where lawful, restrict features, suspend or close seller access, preserve evidence, and cooperate with authorities. Serious safety, fraud, counterfeit, privacy, or legal concerns may lead to immediate action.',
                ],
            },
        ],
    },
    prohibited: {
        eyebrow: 'Marketplace policy',
        title: 'Prohibited items policy',
        summary:
            'Do not list products or services that are unlawful, unsafe, deceptive, exploitative, or inappropriate for this general marketplace.',
        sections: [
            {
                id: 'illegal-and-regulated',
                title: 'Illegal and tightly regulated goods',
                paragraphs: [
                    'Listings must comply with Sri Lankan law and any law applicable to the seller, product, shipment, or transaction. A licence does not guarantee marketplace approval.',
                ],
                bullets: [
                    'Illegal drugs, controlled substances, drug paraphernalia, or prescription medicines sold without lawful authorization.',
                    'Firearms, ammunition, explosives, military weapons, or instructions primarily intended to create them.',
                    'Stolen goods, smuggled goods, unlawfully imported goods, or products subject to sanctions or trade restrictions.',
                    'Government IDs, official documents, credentials, or access devices offered without lawful authority.',
                ],
            },
            {
                id: 'unsafe-products',
                title: 'Unsafe and recalled products',
                paragraphs: [
                    'Do not list recalled, dangerously defective, adulterated, expired, contaminated, or falsely certified products. Safety notices, age restrictions, and legally required warnings must be clear.',
                ],
            },
            {
                id: 'counterfeit-and-rights',
                title: 'Counterfeit and infringing goods',
                paragraphs: [
                    'Counterfeit products, unauthorized replicas presented as genuine, pirated media or software, circumvention tools, and goods that misuse trademarks, copyright, patents, or personality rights are prohibited.',
                ],
            },
            {
                id: 'people-animals-and-content',
                title: 'People, animals, and harmful content',
                paragraphs: [
                    'Human exploitation, sexual services, non-consensual intimate content, hate material, human remains, protected wildlife, unlawful animal sales, and products primarily promoting violence or abuse are prohibited.',
                ],
            },
            {
                id: 'financial-and-digital',
                title: 'Financial and digital abuse',
                paragraphs: [
                    'Do not list stolen payment data, accounts, passwords, personal data, malware, phishing tools, fraudulent investments, money-laundering services, unlicensed financial products, or schemes promising deceptive returns.',
                ],
            },
            {
                id: 'reporting',
                title: 'Reporting a concern',
                paragraphs: [
                    'Email support@prodeals.lk with the listing URL and reason for concern. Do not purchase an item merely to investigate it. ProDeals.lk may remove content while reviewing safety or legality.',
                ],
            },
        ],
    },
};
