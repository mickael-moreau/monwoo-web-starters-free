<?php

namespace MWS\MoonManagerBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use MWS\MoonManagerBundle\Entity\MwsTimeTag;
use MWS\MoonManagerBundle\Form\MwsSurveyJsType;
use MWS\MoonManagerBundle\Repository\MwsTimeSlotRepository;
use MWS\MoonManagerBundle\Repository\MwsTimeTagRepository;
use MWS\MoonManagerBundle\Security\MwsLoginFormAuthenticator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security as SecuAttr;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    '/{_locale<%app.supported_locales%>}/mws-timings',
    options: ['expose' => true],
)]
#[SecuAttr(
    "is_granted('ROLE_USER')",
    statusCode: 401,
    message: MwsLoginFormAuthenticator::t_failToGrantAccess
)]
class MwsTimingController extends AbstractController
{
    public function __construct(
        protected Security $security,
        protected LoggerInterface $logger,
        protected SerializerInterface $serializer,
        protected TranslatorInterface  $translator,
        protected EntityManagerInterface $em,
        protected SluggerInterface $slugger,
    ) {
    }

    #[Route('', name: 'mws_timings_lookup')]
    public function lookup(): Response
    {
        return $this->render('@MoonManager/mws_timing/lookup.html.twig', [
            'controller_name' => 'MwsTimingController',
        ]);
    }

    #[Route('/qualif/{viewTemplate<[^/]*>?}', name: 'mws_timings_qualif')]
    public function qualif(
        $viewTemplate,
        MwsTimeSlotRepository $mwsTimeSlotRepository,
        MwsTimeTagRepository $mwsTimeTagRepository,
        PaginatorInterface $paginator,
        Request $request,
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            // TIPS : redondant, but better if used without routing system secu...
            throw $this->createAccessDeniedException('Only for logged users');
        }

        $requestData = $request->query->all();
        $keyword = $requestData['keyword'] ?? null;
        $searchTags = $requestData['tags'] ?? []; // []);
        $searchTagsToAvoid = $requestData['tagsToAvoid'] ?? []; // []);

        $tagQb = $mwsTimeTagRepository->createQueryBuilder("t");
        $lastSearch = [
            // TIPS urlencode() will use '+' to replace ' ', rawurlencode is RFC one
            "jsonResult" => rawurlencode(json_encode([
                "searchKeyword" => $keyword,
                "searchTags" => $searchTags,
                "searchTagsToAvoid" => $searchTagsToAvoid,
            ])),
            "surveyJsModel" => rawurlencode($this->renderView(
                "@MoonManager/survey_js_models/MwsTimingLookupType.json.twig",
                [
                    'allTimingTags' => array_map(
                        function (MwsTimeTag $tag) {
                            return $tag->getSlug();
                        },
                        $tagQb->where($tagQb->expr()->isNotNull("t.category"))
                            ->getQuery()->getResult()
                    ),
                ]
            )),
        ];
        $filterForm = $this->createForm(MwsSurveyJsType::class, $lastSearch);
        $filterForm->handleRequest($request);

        if ($filterForm->isSubmitted()) {
            $this->logger->debug("Did submit search form");

            if ($filterForm->isValid()) {
                $this->logger->debug("Search form ok");
                // dd($filterForm);
                $surveyAnswers = json_decode(
                    urldecode($filterForm->get('jsonResult')->getData()),
                    true
                );
                $keyword = $surveyAnswers['searchKeyword'] ?? null;
                $searchTags = $surveyAnswers['searchTags'] ?? [];
                $searchTagsToAvoid = $surveyAnswers['searchTagsToAvoid'] ?? [];
                // dd($searchTags);
                return $this->redirectToRoute(
                    'mws_timings_qualif',
                    array_merge($request->query->all(), [
                        "viewTemplate" => $viewTemplate,
                        "keyword" => $keyword,
                        "tags" => $searchTags,
                        "tagsToAvoid" => $searchTagsToAvoid,
                        "page" => 1,
                    ]),
                    Response::HTTP_SEE_OTHER
                );
            }
        }

        $qb = $mwsTimeSlotRepository->createQueryBuilder('o');
        if ($keyword) {
            // TODO : MwsKeyword Data model stuff todo, paid level 2 ocr ?
            // ->setParameter('keyword', '%' . strtolower(str_replace(" ", "", $keyword)) . '%');
        }

        if (count($searchTags) || count($searchTagsToAvoid)) {
            $qb = $qb->innerJoin('o.tags', 'tag');
        }
        if (count($searchTags)) {
            $orClause = '';
            foreach ($searchTags as $idx => $slug) {
                if ($idx) {
                    $orClause .= ' OR ';
                }
                $orClause .= "( :tagSlug$idx = tag.slug )";
                // $orClause .= " AND :tagCategory$idx = tag.categorySlug )";
                $qb->setParameter("tagSlug$idx", $slug);
                // $qb->setParameter("tagCategory$idx", $category);
            }
            $qb = $qb->andWhere($orClause);
        }

        if (count($searchTagsToAvoid)) {
            // dd($searchTagsToAvoid);
            foreach ($searchTagsToAvoid as $idx => $slug) {
                $dql = '';
                $tag = $mwsTimeTagRepository->findOneBy([
                    'slug' => $slug,
                ]);
                $dql .= ":tagToAvoid$idx NOT MEMBER OF o.tags";
                $qb->setParameter("tagToAvoid$idx", $tag);
                // dd($dql);
                $qb = $qb->andWhere($dql);
            }
        }

        $query = $qb->getQuery();
        // dd($query->getResult());    
        $timings = $paginator->paginate(
            $query, /* query NOT result */
            $request->query->getInt('page', 1), /*page number*/
            $request->query->getInt('pageLimit', 448), /*page limit, 28*16 */
        );

        $this->logger->debug("Succeed to list timings");

        return $this->render('@MoonManager/mws_timing/qualif.html.twig', [
            'timings' => $timings,
            'lookupForm' => $filterForm,
            'viewTemplate' => $viewTemplate,
        ]);
    }

    #[Route('reports', name: 'mws_timings_report')]
    public function report(): Response
    {
        return $this->render('@MoonManager/mws_timing/report.html.twig', [
            'controller_name' => 'MwsTimingController',
        ]);
    }

    #[Route('/fetch-media-url', name: 'mws_timing_fetchMediatUrl')]
    public function fetchRootUrl(
        Request $request,
    ): Response {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('Only for logged users');
        }

        $url = $request->query->get('url', null);
        $this->logger->debug("Will fetch url : $url");

        // TODO : secu : filter real path to allowed screenshot folders from .env only ?
        if (false) {
            throw $this->createAccessDeniedException('Media path not allowed');
        }
        
        // Or use : https://symfony.com/doc/current/http_client.html
        $respData = file_get_contents($url);
        $response = new Response($respData);

        // $response->headers->set('Content-Type', 'application/pdf');
        // $mime = [
        //     'json' => 'application/json',
        //     'csv' => 'text/comma-separated-values',
        //     'xml' => 'application/xml',
        //     'yaml' => 'application/yaml',
        // ][$format] ?? 'text/plain';
        // if ($mime) {
        //     $response->headers->set('Content-Type', $mime);
        // }

        // $response->headers->set('Cache-Control', 'no-cache');
        // $response->headers->set('Pragma', 'no-chache');
        // $response->headers->set('Expires', '0');
        return $response;
    }

}