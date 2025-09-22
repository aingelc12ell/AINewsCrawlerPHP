<?php

return [
    [
        'name'      => 'TechCrunch',
        'base_url'  => 'https://techcrunch.com',
        'endpoint'  => '/category/artificial-intelligence/',
        'selectors' => [
            'articles'    => 'li.wp-block-post',
            'title'       => 'h3 a',
            'url'         => 'h3 a',
            'summary'     => 'h3 a',
            'date'        => 'time',
            'date_format' => 'Y-m-d\TH:i:sP',
            'image'       => 'img',
        ],
    ],
    [
        'name'      => 'The Verge',
        'base_url'  => 'https://www.theverge.com',
        'endpoint'  => '/ai-artificial-intelligence',
        'selectors' => [
            'articles'    => 'div.duet--content-cards--content-card',
            'title'       => 'a',
            'url'         => 'a',
            'summary'     => 'p.duet--content-cards--content-card-excerpt',
            'date'        => 'time',
            'date_format' => 'Y-m-d\TH:i:sP',
            'image'       => 'img',
        ],
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
            'image'       => 'img',
        ],
    ],
    [
        'name'      => 'Ars Technica',
        'base_url'  => 'https://arstechnica.com',
        'endpoint'  => '/tag/artificial-intelligence/',
        'selectors' => [
            'articles'    => 'article.post',
            'title'       => 'h2 a',
            'url'         => 'h2 a',
            'summary'     => 'p[class*="leading"]',
            'date'        => 'time',
            'date_format' => 'Y-m-d\TH:i:sP',
            'image'       => 'img',
        ],
    ],
    [
        'name'      => 'The Register',
        'base_url'  => 'https://www.theregister.com',
        'endpoint'  => '/software/ai_ml/',
        'selectors' => [
            'articles'    => 'article',
            'title'       => 'h4',
            'url'         => 'a, a[class*="story_link"]',
            'summary'     => '[class*="standfirst"]',
            'date'        => '[class*="time_stamp"]',
            'date_format' => 'j M H:i',
            'image'       => 'img',
        ],
    ],

    [
        'name'      => 'ZDNet',
        'base_url'  => 'https://www.zdnet.com',
        'endpoint'  => '/topic/artificial-intelligence/',
        'selectors' => [
            'articles'    => 'div[class*="c-listingDefault_item"]',
            'title'       => 'h3, h3[class*="c-listingDefault_title"]',
            'url'         => 'a',
            'summary'     => 'span[class*="c-listingDefault_description"]',
            'date'        => 'time',
            'date_format' => 'Y-m-d\TH:i:sP',
            'image'       => 'img',
            'count'       => 30,
        ],
    ],
    [
        'name'      => 'IEEE Spectrum',
        'base_url'  => 'https://spectrum.ieee.org',
        'endpoint'  => '/topic/artificial-intelligence/',
        'selectors' => [
            'articles'    => 'article',
            'title'       => 'h2 a',
            'url'         => 'h2 a',
            'summary'     => 'h3 p',
            'date'        => 'div[class="social-date"] span',
            'date_format' => 'd M Y',
            'image'       => 'img',
        ],
    ],
    [
        'name'      => 'Analytics India Magazine',
        'base_url'  => 'https://analyticsindiamag.com',
        'endpoint'  => '/category/artificial-intelligence/',
        'selectors' => [
            'articles'    => 'article',
            'title'       => 'h3 a',
            'url'         => 'h3 a',
            'summary'     => 'div[class*="excerpt"] p',
            'date'        => 'span[class="elementor-post-date"]',
            'date_format' => 'd/m/Y',
            'image'       => 'img',
        ],
    ],
    [
        'name'      => 'Synced',
        'base_url'  => 'https://syncedreview.com',
        'endpoint'  => '/tag/artificial-intelligence/',
        'selectors' => [
            'articles'    => 'article.post',
            'title'       => 'h2 a',
            'url'         => 'h2 a',
            'summary'     => 'div.entry-content p',
            'date'        => 'span.entry-date a',
            'date_format' => 'Y-m-d',
            'image'       => 'img',
        ],
    ],

    [
        'name'      => 'MarkTechPost',
        'base_url'  => 'https://www.marktechpost.com',
        'endpoint'  => '/category/artificial-intelligence/',
        'selectors' => [
            'articles'    => 'div.td-block-span6',
            'title'       => 'h3 a',
            'url'         => 'h3 a',
            'summary'     => 'div.td-excerpt',
            'date'        => 'time',
            'date_format' => 'F j, Y',
            'image'       => 'img',
        ],
    ],
    [
        'name'      => 'Towards AI',
        'base_url'  => 'https://towardsai.net',
        'endpoint'  => '/ai/artificial-intelligence',
        'selectors' => [
            'articles'    => 'div.post-item',
            'title'       => 'h3 a',
            'url'         => 'h3 a',
            'summary'     => 'div.post-excerpt p',
            'date'        => 'div.post-date',
            'date_format' => 'F j, Y',
            'image'       => 'img',
        ],
    ],
    [
        'name'      => 'DeepLearning.AI',
        'base_url'  => 'https://www.deeplearning.ai',
        'endpoint'  => '/blog/category/news-and-events/',
        'selectors' => [
            'articles'    => 'article',
            'title'       => 'h2',
            'url'         => 'a',
            'summary'     => 'p',
            'date'        => 'time',
            'date_format' => 'M j, Y',
            'image'       => 'img',
        ],
    ],
    [
        'name'      => 'Tom\'s Hardware',
        'base_url'  => 'https://www.tomshardware.com',
        'endpoint'  => '/tech-industry/artificial-intelligence',
        'selectors' => [
            'articles'    => 'div.listingResults',
            'title'       => 'h3',
            'url'         => 'a',
            'summary'     => 'p.synopsis',
            'date'        => 'time',
            'date_format' => 'M j, Y',
            'image'       => 'img',
        ],
    ],
    [
        'name'      => 'Google AI Blog',
        'base_url'  => 'https://research.google',
        'endpoint'  => '/blog/',
        'selectors' => [
            'articles'    => 'ul.blog-posts-grid__cards li',
            'title'       => 'span.headline-5',
            'url'         => 'a',
            'summary'     => 'span.headline-3',
            'date'        => 'p.glue-label',
            'date_format' => 'F j, Y',
            'image'       => 'img',
        ],
    ],

    /*[
        'name'      => 'FutureTools.IO',
        'base_url'  => 'https://www.futuretools.io',
        'endpoint'  => '/news',
        'selectors' => [
            'articles'    => 'div[role="listitem"]',
            'title'       => 'a',
            'url'         => 'a',
            'summary'     => '',
            'date'        => 'div.news-item div:first',
            'date_format' => 'm.d.y',
            'image'       => 'a img',
        ],
    ],*/
    [
        'name'      => 'The Gradient',
        'base_url'  => 'https://thegradient.pub',
        'endpoint'  => '/',
        'selectors' => [
            'articles'    => 'div.c-post-card',
            'title'       => 'h2',
            'url'         => 'a',
            'summary'     => 'p.excerpt',
            'date'        => 'time',
            'date_format' => 'd.M.Y',
            'image'       => 'img',
        ],
    ],

    [
        'name' => 'NVidia Blog',
        'base_url' => 'https://blogs.nvidia.com',
        'endpoint' => '/blog/category/generative-ai/',
        'selectors' => [
            'articles' => 'article',
            'title' => 'h2 a',
            'url' => 'h2 a',
            'summary' => 'div.article-excerpt p',
            'date' => 'time',
            'datformat' => 'F j, Y',
            'image' => 'div.tile-image-wrapper img'
        ]
    ],

    /*[
        'name' => 'Reuters',
        'base_url' => 'https://www.reuters.com',
        'endpoint' => '/technology/artificial-intelligence/',
        'selectors' => [
            'articles' => 'main section ul li',
            'title' => 'span[data-testid="TitleHeading"]',
            'url' => 'a[data-testid="TitleLink"]',
            'summary' => 'p[data-testid="Description"]',
            'date' => 'time',
            'datformat' => 'F j, Y'
        ]
    ],


        [
            'name' => 'MIT Technology Review',
            'base_url' => 'https://www.technologyreview.com',
            'endpoint' => '/topic/artificial-intelligence/',
            'selectors' => [
                'articles' => 'div[class*="topicFeed__posts"] div[class*="wrapper"]',
                'title' => 'h3',
                'url' => 'a',
                'summary' => 'div[class*="dek"] p',
                'date' => 'time',
                'datformat' => 'F j, Y'
            ]
        ],

        /*[
            'name' => 'VentureBeat',
            'base_url' => 'https://venturebeat.com',
            'endpoint' => '/ai/',
            'selectors' => [
                'articles' => 'article',
                'title' => 'h2 a',
                'url' => 'h2 a',
                'summary' => 'p[class*="text"]',
                'date' => 'time',
                'date_format' => 'Y-m-d\TH:i:sP'
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
            'name' => 'OpenAI Blog',
            'base_url' => 'https://openai.com',
            'endpoint' => '/news/product-releases/',
            'selectors' => [
                'articles' => 'div.group',
                'title' => 'a',
                'url' => 'a',
                'summary' => 'p',
                'date' => 'time',
                'date_format' => 'M j, Y',
                'image' => 'img'
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
                'summary' => 'section.entry p',
                'date' => 'abbr',
                'date_format' => 'F j, Y'
            ]
        ],
        [
            'name' => 'KDnuggets',
            'base_url' => 'https://www.kdnuggets.com',
            'endpoint' => '/tag/artificial-intelligence',
            # 'search_query' => 's=AI', // Search for AI content
            'selectors' => [
                'articles' => 'ul.three_ul li',
                'title' => 'a b',
                'url' => 'a',
                'summary' => 'div',
                'date' => 'font',
                'date_format' => '- M j, Y.'
            ]
        ],
            [
            'name' => 'Data Science Central',
            'base_url' => 'https://www.datasciencecentral.com',
            'endpoint' => '/articles',
            'selectors' => [
                'articles' => 'article.post',
                'title' => 'h2 a',
                'url' => 'h2 a',
                'summary' => 'div.excerpt-wrap p',
                'date' => 'time',
                'date_format' => 'F j, Y',
                'image' => 'a img'
            ]
        ],
       */
];