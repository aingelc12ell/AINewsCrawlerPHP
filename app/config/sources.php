<?php

return [
    /*[
        'name' => 'TechCrunch',
        'base_url' => 'https://techcrunch.com',
        'endpoint' => '/category/artificial-intelligence/',
        'selectors' => [
            'articles' => 'li.wp-block-post',
            'title' => 'h3 a',
            'url' => 'h3 a',
            'summary' => 'h3 a',
            'date' => 'time',
            'date_format' => 'Y-m-d\TH:i:sP',
            'image' => 'figure img'
        ]
    ],*/
    /*[
        'name' => 'The Verge',
        'base_url' => 'https://www.theverge.com',
        'endpoint' => '/ai-artificial-intelligence',
        'selectors' => [
            'articles' => 'div.duet--content-cards--content-card',
            'title' => 'a',
            'url' => 'a',
            'summary' => 'p.duet--content-cards--content-card-excerpt',
            'date' => 'time',
            'date_format' => 'Y-m-d\TH:i:sP'
            'image' => 'img'
        ]
    ],
    [
        'name'      => 'Wired',
        'base_url'  => 'https://www.wired.com',
        'endpoint'  => '/category/artificial-intelligence/',
        'selectors' => [
            'articles'    => 'div.summary-item',
            'title'       => 'h2',
            'url'         => 'a[class*="hed-link"]',
            'summary'     => 'div[data-testid="SummaryItemExcerpt"]',
            'date'        => 'time',
            'date_format' => 'Y-m-d\TH:i:sP',
            'image' => 'picture img',
        ],
    ],
    [
        'name' => 'Ars Technica',
        'base_url' => 'https://arstechnica.com',
        'endpoint' => '/tag/artificial-intelligence/',
        'selectors' => [
            'articles' => 'article.post',
            'title' => 'h2 a',
            'url' => 'h2 a',
            'summary' => 'p[class*="leading"]',
            'date' => 'time',
            'date_format' => 'Y-m-d\TH:i:sP',
            'image' => 'img',
        ]
    ],*/
    [
    'name' => 'The Register',
    'base_url' => 'https://www.theregister.com',
    'endpoint' => '/software/ai_ml/',
    'selectors' => [
        'articles' => 'article',
        'title' => 'h4',
        'url' => 'a, a[class*="story_link"]',
        'summary' => '[class*="standfirst"]',
        'date' => '[class*="time_stamp"]',
        'date_format' => 'j M H:i',
        'image' => 'img',
    ]
],

   /*  [
        'name' => 'VentureBeat',
        'base_url' => 'https://venturebeat.com',
        'endpoint' => '/ai/',
        'selectors' => [
            'articles' => 'article[class*="ArticleList"]',
            'title' => 'h2 a',
            'url' => 'h2 a',
            'summary' => 'p[class*="excerpt"]',
            'date' => 'time',
            'date_format' => 'Y-m-d\TH:i:sP'
        ]
    ],
    [
        'name' => 'ZDNet',
        'base_url' => 'https://www.zdnet.com',
        'endpoint' => '/topic/artificial-intelligence/',
        'selectors' => [
            'articles' => 'div[role="article"]',
            'title' => 'h3 a',
            'url' => 'h3 a',
            'summary' => 'p[class*="summary"]',
            'date' => 'time',
            'date_format' => 'Y-m-d\TH:i:sP'
        ]
    ],
    [
        'name' => 'IEEE Spectrum',
        'base_url' => 'https://spectrum.ieee.org',
        'endpoint' => '/topic/artificial-intelligence/',
        'selectors' => [
            'articles' => 'article.post-item',
            'title' => 'h3 a',
            'url' => 'h3 a',
            'summary' => 'div[itemprop="description"]',
            'date' => 'time',
            'date_format' => 'Y-m-d\TH:i:sP'
        ]
    ],
    [
        'name' => 'Analytics India Magazine',
        'base_url' => 'https://analyticsindiamag.com',
        'endpoint' => '/category/artificial-intelligence/',
        'selectors' => [
            'articles' => 'article',
            'title' => 'h3 a',
            'url' => 'h3 a',
            'summary' => 'div.post-content',
            'date' => 'time',
            'date_format' => 'F j, Y'
        ]
    ],
    [
        'name' => 'Synced',
        'base_url' => 'https://syncedreview.com',
        'endpoint' => '/category/artificial-intelligence/',
        'selectors' => [
            'articles' => 'article.post',
            'title' => 'h2 a',
            'url' => 'h2 a',
            'summary' => 'div.entry-content',
            'date' => 'time',
            'date_format' => 'F j, Y'
        ]
    ],
    [
        'name' => 'AI Trends',
        'base_url' => 'https://www.aitrends.com',
        'endpoint' => '/',
        'search_query' => 's=artificial+intelligence', // Use search for AI content
        'selectors' => [
            'articles' => 'article.post',
            'title' => 'h2 a',
            'url' => 'h2 a',
            'summary' => 'div.entry-content p:first-of-type',
            'date' => 'time',
            'date_format' => 'F j, Y'
        ]
    ],
    [
        'name' => 'MarkTechPost',
        'base_url' => 'https://www.marktechpost.com',
        'endpoint' => '/category/artificial-intelligence/',
        'selectors' => [
            'articles' => 'article',
            'title' => 'h3 a',
            'url' => 'h3 a',
            'summary' => 'div[itemprop="description"]',
            'date' => '.date',
            'date_format' => 'F j, Y'
        ]
    ],
    [
        'name' => 'Towards AI',
        'base_url' => 'https://towardsai.net',
        'endpoint' => '/p/category/artificial-intelligence',
        'selectors' => [
            'articles' => 'article',
            'title' => 'h3 a',
            'url' => 'h3 a',
            'summary' => 'p.excerpt',
            'date' => 'time',
            'date_format' => 'Y-m-d\TH:i:sP'
        ]
    ],
    [
        'name' => 'DeepLearning.AI',
        'base_url' => 'https://www.deeplearning.ai',
        'endpoint' => '/blog/',
        'selectors' => [
            'articles' => 'article',
            'title' => 'h3 a',
            'url' => 'h3 a',
            'summary' => 'p',
            'date' => 'time',
            'date_format' => 'F j, Y'
        ]
    ],
    [
        'name' => 'Google AI Blog',
        'base_url' => 'https://ai.googleblog.com',
        'endpoint' => '/',
        'selectors' => [
            'articles' => 'div.post',
            'title' => 'h3 a',
            'url' => 'h3 a',
            'summary' => 'div.post-body',
            'date' => 'span.publish-date',
            'date_format' => 'F j, Y'
        ]
    ],
    [
        'name' => 'OpenAI Blog',
        'base_url' => 'https://openai.com',
        'endpoint' => '/blog',
        'selectors' => [
            'articles' => 'article',
            'title' => 'h3 a',
            'url' => 'h3 a',
            'summary' => 'p',
            'date' => 'time',
            'date_format' => 'Y-m-d'
        ]
    ],
    [
        'name' => 'Machine Learning Mastery',
        'base_url' => 'https://machinelearningmastery.com',
        'endpoint' => '/blog/',
        'selectors' => [
            'articles' => 'article.post',
            'title' => 'h2 a',
            'url' => 'h2 a',
            'summary' => 'div.entry-content p:first-of-type',
            'date' => 'time',
            'date_format' => 'F j, Y'
        ]
    ],
    [
        'name' => 'KDnuggets',
        'base_url' => 'https://www.kdnuggets.com',
        'endpoint' => '/news',
        'search_query' => 's=AI', // Search for AI content
        'selectors' => [
            'articles' => 'div.li-has-thumb',
            'title' => 'h3 a',
            'url' => 'h3 a',
            'summary' => 'p',
            'date' => 'time',
            'date_format' => 'F j, Y'
        ]
    ],
    [
        'name' => 'Data Science Central',
        'base_url' => 'https://www.datasciencecentral.com',
        'endpoint' => '/blog',
        'selectors' => [
            'articles' => 'div.blogpost',
            'title' => 'h2 a',
            'url' => 'h2 a',
            'summary' => 'div.excerpt',
            'date' => 'span.date',
            'date_format' => 'F j, Y'
        ]
    ],
    [
        'name' => 'The Gradient',
        'base_url' => 'https://thegradient.pub',
        'endpoint' => '/',
        'selectors' => [
            'articles' => 'article.post-preview',
            'title' => 'h3 a',
            'url' => 'h3 a',
            'summary' => 'p.excerpt',
            'date' => 'time',
            'date_format' => 'Y-m-d'
        ]
    ],
    [
        'name' => 'MIT Technology Review',
        'base_url' => 'https://www.technologyreview.com',
        'endpoint' => '/topic/artificial-intelligence/',
        'selectors' => [
            'articles' => 'div[class*="homepageStoryCard__wrapper"]',
            'title' => 'h3[class*="homepageStoryCard__hed"]',
            'url' => 'a[data-event-category="topic-feed"]',
            'summary' => 'div[class*="homepageStoryCard__dek"] p',
            'date' => 'time',
            'date_format' => 'F j, Y'
        ]
    ],*/
];