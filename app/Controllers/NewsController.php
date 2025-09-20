<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class NewsController
{
    private $storageService;
    private $crawlerService;

    public function __construct($storageService, $crawlerService)
    {
        $this->storageService = $storageService;
        $this->crawlerService = $crawlerService;
    }

    public function index(Request $request, Response $response)
    {
        $articles = $this->storageService->getRecentArticles();

        $response->getBody()->write(
            $this->view->render('index.twig', [
                'articles' => $articles,
                'title' => 'AI News Aggregator'
            ])
        );
        return $response;
    }

    public function show(Request $request, Response $response, $args)
    {
        $slug = $args['slug'];
        $article = $this->storageService->getArticleBySlug($slug);

        if (!$article) {
            $response->getBody()->write("Article not found");
            return $response->withStatus(404);
        }

        $response->getBody()->write(
            $this->view->render('article.twig', [
                'article' => $article,
                'title' => $article['title']
            ])
        );
        return $response;
    }

    public function crawl(Request $request, Response $response)
    {
        $result = $this->crawlerService->crawlAllSources();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Crawling completed',
            'stats' => $result
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}