<?php declare(strict_types = 1);

namespace Vairogs\Sitemap\Controller;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vairogs\Sitemap\Builder\Director;
use Vairogs\Sitemap\Builder\XmlBuilder;
use Vairogs\Sitemap\Provider;
use Vairogs\Sitemap\Utils\ErrorResponse;
use function file_exists;
use function file_get_contents;

class SitemapController extends AbstractController
{
    /**
     * @param Request $request
     * @param ValidatorInterface $validator
     * @param Provider|null $provider
     * @param array $options
     *
     * @return Response
     * @Route("/sitemap.xml", methods={"GET"}, defaults={"_format"="xml"}, name="sitemap.xml")
     */
    public function sitemap(Request $request, ValidatorInterface $validator, ?Provider $provider = null, array $options = []): Response
    {
        if (file_exists($sitemap = getcwd() . '/sitemap.xml')) {
            return new Response(file_get_contents($sitemap));
        }

        if (null === $provider || (false === $options['enabled'])) {
            throw new NotFoundHttpException('To use VairogsSitemap, you must enable it and provide a Provider');
        }

        $model = $provider->populate($request->getSchemeAndHttpHost());
        $errors = $validator->validate($model);
        if ($errors->count()) {
            return (new ErrorResponse($errors))->getResponse();
        }

        return new Response((new Director(''))->build(new XmlBuilder($model)));
    }
}