<?php

namespace MWS\MoonManagerBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use MWS\MoonManagerBundle\Entity\MwsTimeSlot;
use MWS\MoonManagerBundle\Entity\MwsTimeTag;
use MWS\MoonManagerBundle\Form\MwsSurveyJsType;
use MWS\MoonManagerBundle\Repository\MwsTimeQualifRepository;
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
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
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

    #[Route('/qualif/view/{viewTemplate<[^/]*>?}', name: 'mws_timings_qualif')]
    public function qualif(
        $viewTemplate,
        MwsTimeSlotRepository $mwsTimeSlotRepository,
        MwsTimeTagRepository $mwsTimeTagRepository,
        MwsTimeQualifRepository $mwsTimeQualifRepository,
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

        $qb = $mwsTimeSlotRepository->createQueryBuilder('t');
        if ($keyword) {
            // TODO : MwsKeyword Data model stuff todo, paid level 2 ocr ?
            // ->setParameter('keyword', '%' . strtolower(str_replace(" ", "", $keyword)) . '%');
        }

        if (count($searchTags) || count($searchTagsToAvoid)) {
            $qb = $qb->innerJoin('t.tags', 'tag');
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
                $dql .= ":tagToAvoid$idx NOT MEMBER OF t.tags";
                $qb->setParameter("tagToAvoid$idx", $tag);
                // dd($dql);
                $qb = $qb->andWhere($dql);
            }
        }

        $qb->orderBy("t.sourceTimeGMT", "ASC");

        $query = $qb->getQuery();
        // dd($query->getResult());    
        $timings = $paginator->paginate(
            $query, /* query NOT result */
            $request->query->getInt('page', 1), /*page number*/
            // $request->query->getInt('pageLimit', 448), /*page limit, 28*16 */
            $request->query->getInt('pageLimit', 124), /*page limit */
        );

        $this->logger->debug("Succeed to list timings");

        $timeQualifs = $mwsTimeQualifRepository->findAll();
        $allTagsList = $mwsTimeTagRepository->findAll();
        // $allTagsList = $mwsTimeTagRepository->findBy([], [
        //     // TODO : translated label imports/edits (multilingues)
        //     'label' => 'ASC'
        // ]);
        // TODO : no DQL nat sort ?
        // https://www.mysqltutorial.org/mysql-basics/mysql-natural-sorting/
        // https://sqlite.org/forum/info/d5cf6c6317dd7e7f
        // https://stackoverflow.com/questions/20431345/naturally-sort-a-multi-dimensional-array-by-key
        array_multisort(
            // array_keys($allTagsList),
            array_map(function ($t) {
                return $t->getLabel();
            }, $allTagsList),
            SORT_NATURAL | SORT_FLAG_CASE,
            $allTagsList
        );

        return $this->render('@MoonManager/mws_timing/qualif.html.twig', [
            'timings' => $timings,
            'allTagsList' => $allTagsList,
            'timeQualifs' => $timeQualifs,
            'lookupForm' => $filterForm,
            'viewTemplate' => $viewTemplate,
        ]);
    }

    #[Route('/report/{viewTemplate<[^/]*>?}', name: 'mws_timings_report')]
    public function report(
        $viewTemplate,
        MwsTimeSlotRepository $mwsTimeSlotRepository,
        MwsTimeTagRepository $mwsTimeTagRepository,
        MwsTimeQualifRepository $mwsTimeQualifRepository,
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
        // $timingTags = array_map(
        //     function (MwsTimeTag $tag) {
        //         return $tag->getSlug();
        //     },
        //     $tagQb // ->where($tagQb->expr()->isNotNull("t.category"))
        //         ->getQuery()->getResult()
        // );
        $timingTags = $tagQb->getQuery()->getResult();

        $lastSearch = [
            // TIPS urlencode() will use '+' to replace ' ', rawurlencode is RFC one
            "jsonResult" => rawurlencode(json_encode([
                // TODO : createForm 
                // $form = $formFactory->createNamed('custom_form_name', CustomType::class);
                // or : https://stackoverflow.com/questions/37005899/symfony3-is-it-possible-to-change-the-name-of-a-form
                // add public function getBlockPrefix() in form type....
                "MwsTimingLookupType" => true,
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
                        $timingTags
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

                // dd($filterForm->get('surveyJsModel'));
                // dd($filterForm);
                $surveyAnswers = json_decode(
                    urldecode($filterForm->get('jsonResult')->getData()),
                    true
                );
                if ($surveyAnswers['MwsTimingLookupType'] ?? false) {
                    $keyword = $surveyAnswers['searchKeyword'] ?? null;
                    $searchTags = $surveyAnswers['searchTags'] ?? [];
                    $searchTagsToAvoid = $surveyAnswers['searchTagsToAvoid'] ?? [];
                    // dd($searchTags);
                    return $this->redirectToRoute(
                        'mws_timings_report',
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
        }

        $reportTagsLvl1 = $requestData['lvl1Tags'] ?? []; // []);
        $reportTagsLvl2 = $requestData['lvl2Tags'] ?? []; // []);
        $reportTagsLvl3 = $requestData['lvl3Tags'] ?? []; // []);
        $reportTagsLvl4 = $requestData['lvl4Tags'] ?? []; // []);
        $reportTagsLvl5 = $requestData['lvl5Tags'] ?? []; // []);

        $lastReport = [
            // TIPS urlencode() will use '+' to replace ' ', rawurlencode is RFC one
            "jsonResult" => rawurlencode(json_encode([
                "MwsTimingReportType" => true,
                "lvl1Tags" => $reportTagsLvl1,
                "lvl2Tags" => $reportTagsLvl2,
                "lvl3Tags" => $reportTagsLvl3,
                "lvl4Tags" => $reportTagsLvl4,
                "lvl5Tags" => $reportTagsLvl5,
            ])),
            "surveyJsModel" => rawurlencode($this->renderView(
                "@MoonManager/survey_js_models/MwsTimingReportType.json.twig",
                [
                    'allTimingTags' => array_map(
                        function (MwsTimeTag $tag) {
                            return $tag->getSlug();
                        },
                        $timingTags
                    ),
                ]
            )),
        ];
        $reportForm = $this->createForm(MwsSurveyJsType::class, $lastReport);
        $reportForm->handleRequest($request);

        if ($reportForm->isSubmitted()) {
            $this->logger->debug("Did submit search form");

            if ($reportForm->isValid()) {
                $this->logger->debug("Search form ok");
                // dd($reportForm);
                $surveyAnswers = json_decode(
                    urldecode($reportForm->get('jsonResult')->getData()),
                    true
                );
                if ($surveyAnswers['MwsTimingReportType'] ?? false) {
                    $reportTagsLvl1 = $surveyAnswers['lvl1Tags'] ?? [];
                    $reportTagsLvl2 = $surveyAnswers['lvl2Tags'] ?? [];
                    $reportTagsLvl3 = $surveyAnswers['lvl3Tags'] ?? [];
                    $reportTagsLvl4 = $surveyAnswers['lvl4Tags'] ?? [];
                    $reportTagsLvl5 = $surveyAnswers['lvl5Tags'] ?? [];
                    // dd($reportTagsLvl1);
                    return $this->redirectToRoute(
                        'mws_timings_report',
                        array_merge($request->query->all(), [
                            "viewTemplate" => $viewTemplate,
                            "page" => 1,
                            "lvl1Tags" => $reportTagsLvl1,
                            "lvl2Tags" => $reportTagsLvl2,
                            "lvl3Tags" => $reportTagsLvl3,
                            "lvl4Tags" => $reportTagsLvl4,
                            "lvl5Tags" => $reportTagsLvl5,
                        ]),
                        Response::HTTP_SEE_OTHER
                    );
                }
            }
        }

        $qb = $mwsTimeSlotRepository->createQueryBuilder('t');
        // https://stackoverflow.com/questions/17878237/doctrine-cannot-select-entity-through-identification-variables-without-choosing
        // ->from(MwsTimeTag::class, 'tag');
        // CONCAT_WS('-', tag.slug, tag.slug) as tags,
        // strftime('%W', t.sourceTimeGMT) as sourceWeekOfYear,

        if ($keyword) {
            // TODO : MwsKeyword Data model stuff todo, paid level 2 ocr ?
            // ->setParameter('keyword', '%' . strtolower(str_replace(" ", "", $keyword)) . '%');
        }

        if (count($searchTags)) {
            $orClause = '';
            foreach ($searchTags as $idx => $slug) {
                if ($idx) {
                    $orClause .= ' OR ';
                }
                // $orClause .= "( :tagSlug$idx = tag.slug )";
                // // $orClause .= " AND :tagCategory$idx = tag.categorySlug )";
                // $qb->setParameter("tagSlug$idx", $slug);
                // $qb->setParameter("tagCategory$idx", $category);
                $tag = $mwsTimeTagRepository->findOneBy([
                    'slug' => $slug,
                ]);
                $orClause .= "( :tag$idx MEMBER OF t.tags )";
                $qb->setParameter("tag$idx", $tag);
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
                $dql .= ":tagToAvoid$idx NOT MEMBER OF t.tags";
                $qb->setParameter("tagToAvoid$idx", $tag);
                // dd($dql);
                $qb = $qb->andWhere($dql);
            }
        }

        $qb->orderBy("t.sourceTimeGMT", "ASC");

        // $query = $qb->getQuery();
        // $qb = $qb->innerJoin('t.tags', 'tag');
        // https://stackoverflow.com/questions/45756622/doctrine-query-with-nullable-optional-join
        $qb = $qb->leftJoin('t.tags', 'tag');

        // Fetching 'source' is too slow, and splitting with , might have issue with ','...
        // GROUP_CONCAT(t.source) as source,            
        // strftime('%Y-%m-%d %H:%M:%S', t.sourceTimeGMT) as sourceTimeGMT,


        // https://www.php.net/manual/fr/function.strftime.php
        $qb = $qb->select("
            count(t) as count,
            strftime('%Y-%m-%d', t.sourceTimeGMT) as sourceDate,
            strftime('%s', t.sourceTimeGMT) as sourceTimeGMTstamp,
            strftime('%Y', t.sourceTimeGMT) as sourceYear,
            strftime('%m', t.sourceTimeGMT) as sourceMonth,
            strftime('%d', t.sourceTimeGMT) as sourceWeekOfYear,
            GROUP_CONCAT(tag.slug) as tags,
            GROUP_CONCAT(tag.pricePerHr) as pricesPerHr,
            GROUP_CONCAT(t.sourceStamp) as sourceStamps,
            GROUP_CONCAT(tag.label) as labels,
            GROUP_CONCAT(t.rangeDayIdxBy10Min) as allRangeDayIdxBy10Min,
            GROUP_CONCAT(t.id) as ids
        ");

        $qb->groupBy("sourceYear");
        $qb->addGroupBy("sourceMonth");
        $qb->addGroupBy("sourceDate");
        $qb->addGroupBy("tag.slug");
        // $qb->addGroupBy("t.rangeDayIdxBy10Min");

        $groupedQuery = $qb->getQuery();
        // dd($query->getDQL());    
        // dd($query->getResult());    
        // $timingsReport = $paginator->paginate(
        //     $groupedQuery, /* query NOT result */
        //     $request->query->getInt('page', 1), /*page number*/
        //     // $request->query->getInt('pageLimit', 448), /*page limit, 28*16 */
        //     $request->query->getInt('pageLimit', 200000), /*page limit */
        // ); // TIPS : will REMOVE the groupBy etc.... (select too ?)...
        $timingsReport = $groupedQuery->getResult();

        // // TODO : too slow :
        // $timingsPage = $paginator->paginate(
        //     $query, /* query NOT result */
        //     $request->query->getInt('page', 1), /*page number*/
        //     // $request->query->getInt('pageLimit', 448), /*page limit, 28*16 */
        //     $request->query->getInt('pageLimit', 200000), /*page limit */
        // );
        // // TODO : too slow :
        // // $timings = iterator_to_array($timingsPage->getItems());
        // // $timingsById = [];
        // // array_walk(
        // //     $timings,
        // //     function ($t) use (&$timingsById) {
        // //         $timingsById[$t->getId()] = $t;
        // //     }
        // // );

        $this->logger->debug("Succeed to list timings");

        return $this->render('@MoonManager/mws_timing/report.html.twig', [
            'timingsReport' => $timingsReport,
            // 'timingsById' => $timingsById,
            // 'timings' => $timingsPage,
            'timingTags' => $timingTags,
            'lookupForm' => $filterForm,
            'report' => [
                "lvl1Tags" => $reportTagsLvl1,
                "lvl2Tags" => $reportTagsLvl2,
                "lvl3Tags" => $reportTagsLvl3,
                "lvl4Tags" => $reportTagsLvl4,
                "lvl5Tags" => $reportTagsLvl5,
            ],
            'reportForm' => $reportForm,
            'viewTemplate' => $viewTemplate,
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
        $keepOriginalSize = $request->query->get('keepOriginalSize', null);
        $this->logger->debug("Will fetch url : $url");

        // TODO : secu : filter real path to allowed screenshot folders from .env only ?
        if (false) {
            throw $this->createAccessDeniedException('Media path not allowed');
        }

        // Or use : https://symfony.com/doc/current/http_client.html
        // $respData = file_get_contents($url);

        // TODO : for efficiency, resize image before usage :
        // https://www.php.net/manual/en/imagick.resizeimage.php
        // or :
        // https://stackoverflow.com/questions/14649645/resize-image-in-php
        // https://www.php.net/manual/en/function.imagecopyresized.php (GD)
        // 
        // and/or js size ? : 
        // https://github.com/fabricjs/fabric.js
        // https://imagekit.io/blog/how-to-resize-image-in-javascript/
        // https://stackoverflow.com/questions/39762102/resizing-image-while-printing-html
        // $imagick = new \Imagick(realpath($url));
        if ($keepOriginalSize) {
            // TODO : filter url outside of allowed server folders ?
            $respData = file_get_contents($url);
        } else {
            $imagick = new \Imagick($url);
            $targetW = 150; // px, // TODO : from session or db config params
            $factor = $targetW / $imagick->getImageWidth();
            $imagick->resizeImage( // TODO : desactivate with param for qualif detail view ?
                $imagick->getImageWidth() * $factor,
                $imagick->getImageHeight() * $factor,
                // https://urmaul.com/blog/imagick-filters-comparison/
                \Imagick::FILTER_CATROM,
                0
            );
            $imagick->setImageCompressionQuality(0);
            // https://www.php.net/manual/fr/imagick.resizeimage.php#94493
            // FILTER_POINT is 4 times faster
            // $imagick->scaleimage(
            //     $imagick->getImageWidth() * 4,
            //     $imagick->getImageHeight() * 4
            // );
            $respData = $imagick->getImageBlob();
        }

        $response = new Response($respData);
        $response->headers->set('Content-Type', 'image/jpg');
        // https://symfony.com/doc/6.2/the-fast-track/en/21-cache.html
        $maxAge = 3600 * 5;
        $response->setSharedMaxAge($maxAge);
        // max-age=604800, must-revalidate
        $response->headers->set('Cache-Control', "max-age=$maxAge");
        $response->headers->set('Expires', "$maxAge");
        // For legacy browsers (no cache):
        // $response->headers->set('Pragma', 'no-chache');

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

    #[Route(
        '/delete-all/{viewTemplate<[^/]*>?}',
        name: 'mws_timing_delete_all',
        methods: ['POST'],
    )]
    public function deleteAll(
        string|null $viewTemplate,
        Request $request,
    ): Response {
        $user = $this->getUser();
        // TIPS : firewall, middleware or security guard can also
        //        do the job. Double secu prefered ? :
        if (!$user) { // TODO : only for admin too ?
            $this->logger->debug("Fail auth with", [$request]);
            throw $this->createAccessDeniedException('Only for logged users');
        }
        $csrf = $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('mws-csrf-timing-delete-all', $csrf)) {
            $this->logger->debug("Fail csrf with", [$csrf, $request]);
            throw $this->createAccessDeniedException('CSRF Expired');
        }

        $qb = $this->em->createQueryBuilder()
            ->delete(MwsTimeSlot::class, 't');
        $query = $qb->getQuery();
        $query->execute();
        $this->em->flush();

        return $this->redirectToRoute(
            'mws_timings_qualif',
            [ // array_merge($request->query->all(), [
                "viewTemplate" => $viewTemplate,
                "page" => 1,
            ], //),
            Response::HTTP_SEE_OTHER
        );
    }

    #[Route(
        '/tag/list/{viewTemplate<[^/]*>?}',
        name: 'mws_timing_tag_list',
        methods: ['GET'],
        defaults: [
            'viewTemplate' => null,
        ],
    )]
    public function tagList(
        string|null $viewTemplate,
        Request $request,
        PaginatorInterface $paginator,
        MwsTimeSlotRepository $mwsTimeSlotRepository,
        MwsTimeTagRepository $mwsTimeTagRepository,
    ): Response {
        $user = $this->getUser();
        // TIPS : firewall, middleware or security guard can also
        //        do the job. Double secu prefered ? :
        if (!$user) {
            $this->logger->debug("Fail auth with", [$request]);
            throw $this->createAccessDeniedException('Only for logged users');
        }
        // $tags = $mwsTimeTagRepository->findAll();
        // array_multisort(
        //     // array_keys($allTagsList),
        //     array_map(function($t) {
        //         return $t->getLabel();
        //     }, $tags),
        //     SORT_NATURAL | SORT_FLAG_CASE,
        //     $tags
        // );

        // dd($this->serializer->serialize($tags, 'yaml'));
        //  TODO : More efficient to 'groupBy' Query with total amount.

        // Fetching all ids is too much time consuming....
        // $tagsSerialized = $this->serializer->serialize($tags, 'yaml', [
        //     'groups' => 'withDeepIds', // TODO : group annotation do not go over ignore annotation
        //     // only below work :
        //     // AbstractNormalizer::ATTRIBUTES => [
        //     //     'id', 'mwsTimeTags',  'mwsTimeQualifs'],
        //     AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function (object $object, string $format, array $context): string {
        //                 // return "**" . (string)$object . "**";
        //                 return $object ? ($object->getId() ?? "****") : '-****-';
        //             },
        // ]);
        // dd($tagsSerialized);

        $qb = $mwsTimeTagRepository->createQueryBuilder('t');

        // $qb->orderBy("t.sourceTimeGMT", "ASC");
        // https://stackoverflow.com/questions/45756622/doctrine-query-with-nullable-optional-join
        $qb = $qb->leftJoin('t.mwsTimeSlots', 's');
        $qb = $qb->leftJoin('t.mwsTimeTags', 'c');
        $qb = $qb->leftJoin('t.mwsTimeQualifs', 'q');

        // https://www.php.net/manual/fr/function.strftime.php
        // count(a.mwsTimeTags) as categoriesCount,
        // count(t.mwsTimeSlots) as tSlotCount,
        // https://stackoverflow.com/questions/3679777/how-to-count-one-to-many-relationships
        // count(s.id) as tSlotCount,
        // count(c.id) as categoriesCount,
        // count(q.id) as tQualifCount
        // $qb->expr()->countDistinct('c.id')
        $qb = $qb->select("
        t as self,
        COUNT(DISTINCT c.id) as categoriesCount,
        COUNT(DISTINCT s.id) as tSlotCount,
        COUNT(DISTINCT q.id) as tQualifCount
        ");

        $qb->addGroupBy("t.id");

        // paginator ? to allow re orders ? may be too much, get param ? :
        // + multi sort ?
        // $timings = $paginator->paginate(
        //     $query, /* query NOT result */
        //     $request->query->getInt('page', 1), /*page number*/
        //     // $request->query->getInt('pageLimit', 448), /*page limit, 28*16 */
        //     $request->query->getInt('pageLimit', 124), /*page limit */
        // );

        $qb->orderBy('t.slug');
        // $qb->orderBy('tQualifCount', 'DESC');
        // $qb->addOrderBy('tSlotCount');
        // $qb->addOrderBy('categoriesCount');

        $tagsGrouped = $qb->getQuery()->getResult();
        // dd($tagsGrouped );
        $tags = array_map(function ($g) {
            return $g['self'];
            // return $g[0];
        }, $tagsGrouped);

        return $this->render('@MoonManager/mws_timing/tags.html.twig', [
            'tags' => $tags,
            // 'tagsSerialized' => $tagsSerialized,
            'tagsGrouped' => $tagsGrouped,
            'viewTemplate' => $viewTemplate,
        ]);
    }

    #[Route(
        '/tag/add/{viewTemplate<[^/]*>?}',
        name: 'mws_timing_tag_add',
        methods: ['POST'],
        defaults: [
            'viewTemplate' => null,
        ],
    )]
    public function tagAdd(
        string|null $viewTemplate,
        Request $request,
        MwsTimeSlotRepository $mwsTimeSlotRepository,
        MwsTimeTagRepository $mwsTimeTagRepository,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $user = $this->getUser();
        // TIPS : firewall, middleware or security guard can also
        //        do the job. Double secu prefered ? :
        if (!$user) {
            $this->logger->debug("Fail auth with", [$request]);
            throw $this->createAccessDeniedException('Only for logged users');
        }
        $csrf = $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('mws-csrf-timing-tag-add', $csrf)) {
            $this->logger->debug("Fail csrf with", [$csrf, $request]);
            throw $this->createAccessDeniedException('CSRF Expired');
        }
        $tagSlug = $request->request->get('tagSlug');
        $tag = $mwsTimeTagRepository->findOneBy([
            "slug" => $tagSlug,
        ]);
        if (!$tag) {
            throw $this->createNotFoundException("Unknow time tag slug [$tagSlug]");
        }
        $timeSlotId = $request->request->get('timeSlotId');
        $timeSlot = $mwsTimeSlotRepository->findOneBy([
            'id' => $timeSlotId,
        ]);
        if (!$timeSlot) {
            throw $this->createNotFoundException("Unknow time slot id [$timeSlotId]");
        }
        // dd($tag);
        $timeSlot->addTag($tag);

        $this->em->persist($timeSlot);
        $this->em->flush();

        return $this->json([
            'newTags' => $timeSlot->getTags(),
            'newCsrf' => $csrfTokenManager->getToken('mws-csrf-timing-tag-add')->getValue(),
            'viewTemplate' => $viewTemplate,
        ]);
    }

    #[Route(
        '/tag/update/{viewTemplate<[^/]*>?}',
        name: 'mws_timing_tag_update',
        methods: ['POST'],
        defaults: [
            'viewTemplate' => null,
        ],
    )]
    public function tagUpdate(
        string|null $viewTemplate,
        Request $request,
        MwsTimeSlotRepository $mwsTimeSlotRepository,
        MwsTimeTagRepository $mwsTimeTagRepository,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $user = $this->getUser();
        // TIPS : firewall, middleware or security guard can also
        //        do the job. Double secu prefered ? :
        if (!$user) {
            $this->logger->debug("Fail auth with", [$request]);
            throw $this->createAccessDeniedException('Only for logged users');
        }
        $csrf = $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('mws-csrf-timing-tag-update', $csrf)) {
            $this->logger->debug("Fail csrf with", [$csrf, $request]);
            throw $this->createAccessDeniedException('CSRF Expired');
        }
        $tagData = $request->request->get('timeTag');
        // $tagData = get_object_vars($tagData);
        // TODO : use serializer deserialize ?
        $tagData = json_decode($tagData, true);
        // dd($tagData);
        $criteria = [
            "slug" => $tagData['slug'] ?? null,
        ];
        // TIPS : For tag update, try ID if exist
        if ($tagData['id'] ?? false) {
            $criteria = [
                "id" => $tagData['id'],
            ];    
        }
        $tag = count($criteria)
        ? $mwsTimeTagRepository->findOneBy($criteria)
        : null;
        if (!$tag) {
            $tag = new MwsTimeTag();
        }

        $sync = function ($path) use (&$tag, &$tagData) {
            $set = 'set' . ucfirst($path);
            // $get = 'get' . ucfirst($path);
            // if(!method_exists($tag, $get)) {
            //     $get = 'is' . ucfirst($path);
            // }
            // $v =  $tag->$get();
            $v =  $tagData[$path] ?? null;
            if (null !== $v) {
                $tag->$set($v);
            }
        };

        $sync('slug');
        $sync('label');
        $sync('description');
        if ($tagData['category'] ?? false) {
            $categorySlug = $tagData['category'];
            $category = $mwsTimeTagRepository->findOneBy([
                "slug" => $categorySlug,
            ]);
            $tagData['category'] = $category;
        }
        $sync('category');
        $sync('pricePerHr');
        $sync('pricePerHrRules');
        // $tag->addTag($tag);

        $this->em->persist($tag);
        $this->em->flush();

        return $this->json([
            'updatedTag' => $tag,
            'newCsrf' => $csrfTokenManager->getToken('mws-csrf-timing-tag-update')->getValue(),
            'viewTemplate' => $viewTemplate,
        ]);
    }

    #[Route(
        '/tag/delete/{viewTemplate<[^/]*>?}',
        name: 'mws_timing_tag_delete',
        methods: ['POST'],
        defaults: [
            'viewTemplate' => null,
        ],
    )]
    public function tagDelete(
        string|null $viewTemplate,
        Request $request,
        MwsTimeSlotRepository $mwsTimeSlotRepository,
        MwsTimeTagRepository $mwsTimeTagRepository,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $user = $this->getUser();
        // TIPS : firewall, middleware or security guard can also
        //        do the job. Double secu prefered ? :
        if (!$user) {
            $this->logger->debug("Fail auth with", [$request]);
            throw $this->createAccessDeniedException('Only for logged users');
        }
        $csrf = $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('mws-csrf-timing-tag-delete', $csrf)) {
            $this->logger->debug("Fail csrf with", [$csrf, $request]);
            throw $this->createAccessDeniedException('CSRF Expired');
        }
        $tagSlug = $request->request->get('tagSlug');
        $tag = $mwsTimeTagRepository->findOneBy([
            "slug" => $tagSlug,
        ]);
        if (!$tag) {
            throw $this->createNotFoundException("Unknow time tag slug [$tagSlug]");
        }
        $timeSlotId = $request->request->get('timeSlotId');
        $timeSlot = $mwsTimeSlotRepository->findOneBy([
            'id' => $timeSlotId,
        ]);
        if (!$timeSlot) {
            throw $this->createNotFoundException("Unknow time slot id [$timeSlotId]");
        }
        // dd($tag);

        $timeSlot->removeTag($tag); // TODO : MUST set inverse relation ship ? but no same issue with import ?
        $this->em->persist($timeSlot);
        $this->em->flush();

        return $this->json([
            'newTags' => $timeSlot->getTags(),
            'newCsrf' => $csrfTokenManager->getToken('mws-csrf-timing-tag-delete')->getValue(),
            'viewTemplate' => $viewTemplate,
        ]);
    }

    #[Route(
        '/tag/delete-and-clean/{viewTemplate<[^/]*>?}',
        name: 'mws_timing_tag_delete_and_clean',
        methods: ['POST'],
        defaults: [
            'viewTemplate' => null,
        ],
    )]
    public function tagDeleteAndClean(
        string|null $viewTemplate,
        Request $request,
        MwsTimeSlotRepository $mwsTimeSlotRepository,
        MwsTimeTagRepository $mwsTimeTagRepository,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $user = $this->getUser();
        // TIPS : firewall, middleware or security guard can also
        //        do the job. Double secu prefered ? :
        if (!$user) {
            $this->logger->debug("Fail auth with", [$request]);
            throw $this->createAccessDeniedException('Only for logged users');
        }
        $csrf = $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('mws-csrf-timing-tag-delete-and-clean', $csrf)) {
            $this->logger->debug("Fail csrf with", [$csrf, $request]);
            throw $this->createAccessDeniedException('CSRF Expired');
        }
        $tagId = $request->request->get('tagId');
        $tag = $mwsTimeTagRepository->findOneBy([
            "id" => $tagId,
        ]);
        if (!$tag) {
            throw $this->createNotFoundException("Unknow time tag at id [$tagId]");
        }

        $qb = $this->em->createQueryBuilder()
            // ->delete('MoonManagerBundle:MwsUser', 'u'); // Depreciated syntax in 6.3 ? sound to work...         
            ->delete(MwsTimeTag::class, 't')
            ->where('t.id = :tId')
            ->setParameter('tId', $tag->getId());

        // TIPS : no need to cleanup invert relation ship
        // DQL did take care of if...

        // ->select('u')                
        // ->from('App:MwsUser', 'u')                
        // ->where('u.xx = :xx')
        // ->setParameter('xx', $xx);

        $query = $qb->getQuery();
        dump($query->getSql());
        $resp = $query->execute();
        dump($resp);

        $this->em->flush();

        return $this->json([
            'newCsrf' => $csrfTokenManager->getToken('mws-csrf-timing-tag-delete-and-clean')->getValue(),
            'viewTemplate' => $viewTemplate,
        ]);
    }

    #[Route(
        '/tag/remove-all/{viewTemplate<[^/]*>?}',
        name: 'mws_timing_tag_remove_all',
        methods: ['POST'],
        defaults: [
            'viewTemplate' => null,
        ],
    )]
    public function tagRemoveAll(
        string|null $viewTemplate,
        Request $request,
        MwsTimeSlotRepository $mwsTimeSlotRepository,
        MwsTimeTagRepository $mwsTimeTagRepository,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $user = $this->getUser();
        // TIPS : firewall, middleware or security guard can also
        //        do the job. Double secu prefered ? :
        if (!$user) {
            $this->logger->debug("Fail auth with", [$request]);
            throw $this->createAccessDeniedException('Only for logged users');
        }
        $csrf = $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('mws-csrf-timing-tag-remove-all', $csrf)) {
            $this->logger->debug("Fail csrf with", [$csrf, $request]);
            throw $this->createAccessDeniedException('CSRF Expired');
        }
        $timeSlotId = $request->request->get('timeSlotId');
        $timeSlot = $mwsTimeSlotRepository->findOneBy([
            'id' => $timeSlotId,
        ]);
        if (!$timeSlot) {
            throw $this->createNotFoundException("Unknow time slot id [$timeSlotId]");
        }
        // dd($tag);
        $timeSlot->getTags()->clear();

        $this->em->persist($timeSlot);
        $this->em->flush();

        return $this->json([
            'newTags' => $timeSlot->getTags(),
            'newCsrf' => $csrfTokenManager->getToken('mws-csrf-timing-tag-remove-all')->getValue(),
            'viewTemplate' => $viewTemplate,
        ]);
    }

    #[Route(
        '/tag/migrate-to/{viewTemplate<[^/]*>?}',
        name: 'mws_timing_tag_migrate_to',
        methods: ['POST'],
        defaults: [
            'viewTemplate' => null,
        ],
    )]
    public function tagMigrateTo(
        string|null $viewTemplate,
        Request $request,
        MwsTimeSlotRepository $mwsTimeSlotRepository,
        MwsTimeTagRepository $mwsTimeTagRepository,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $user = $this->getUser();
        // TIPS : firewall, middleware or security guard can also
        //        do the job. Double secu prefered ? :
        if (!$user) {
            $this->logger->debug("Fail auth with", [$request]);
            throw $this->createAccessDeniedException('Only for logged users');
        }
        $csrf = $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('mws-csrf-timing-tag-migrate-to', $csrf)) {
            $this->logger->debug("Fail csrf with", [$csrf, $request]);
            throw $this->createAccessDeniedException('CSRF Expired');
        }
        $tagFromId = $request->request->get('tagFromId');
        $tagToId = $request->request->get('tagToId');
        $tagFrom = $mwsTimeTagRepository->findOneBy([
            "id" => $tagFromId,
        ]);
        $tagTo = $mwsTimeTagRepository->findOneBy([
            "id" => $tagToId,
        ]);
        if (!$tagFrom) {
            throw $this->createNotFoundException("Unknow time tag from id [$tagFromId]");
        }
        if (!$tagTo) {
            throw $this->createNotFoundException("Unknow time tag from id [$tagToId]");
        }

        foreach ($tagFrom->getMwsTimeTags() as $categoryFrom) {
            $categoryFrom->setCategory($tagTo);
        }
        $this->em->flush();
        foreach ($tagFrom->getMwsTimeSlots() as $tSlot) {
            $tSlot->addTag($tagTo);
        }
        $this->em->flush();
        foreach ($tagFrom->getMwsTimeQualifs() as $tQualif) {
            $tQualif->addTimeTag($tagTo);
        }
        $this->em->flush();

        // Then delete self, will cascade remove with dql :
        $qb = $this->em->createQueryBuilder()
            ->delete(MwsTimeTag::class, 't')
            ->where('t.id = :tId')
            ->setParameter('tId', $tagFrom->getId());
        // TIPS : no need to cleanup invert relation ship
        // DQL did take care of if...
        $query = $qb->getQuery();
        $resp = $query->execute();

        $this->em->flush();

        return $this->json([
            'newCsrf' => $csrfTokenManager->getToken('mws-csrf-timing-tag-migrate-to')->getValue(),
            'viewTemplate' => $viewTemplate,
        ]);
    }

    #[Route(
        '/qualif/toggle/{viewTemplate<[^/]*>?}',
        name: 'mws_timing_qualif_toggle',
        methods: ['POST'],
        defaults: [
            'viewTemplate' => null,
        ],
    )]
    public function qualifToggle(
        string|null $viewTemplate,
        Request $request,
        MwsTimeSlotRepository $mwsTimeSlotRepository,
        MwsTimeTagRepository $mwsTimeTagRepository,
        MwsTimeQualifRepository $mwsTimeQualifRepository,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $user = $this->getUser();
        // TIPS : firewall, middleware or security guard can also
        //        do the job. Double secu prefered ? :
        if (!$user) {
            $this->logger->debug("Fail auth with", [$request]);
            throw $this->createAccessDeniedException('Only for logged users');
        }
        $csrf = $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('mws-csrf-timing-qualif-toggle', $csrf)) {
            $this->logger->debug("Fail csrf with", [$csrf, $request]);
            throw $this->createAccessDeniedException('CSRF Expired');
        }
        $qualifId = $request->request->get('qualifId');
        $qualif = $mwsTimeQualifRepository->findOneBy([
            "id" => $qualifId,
        ]);
        if (!$qualif) {
            throw $this->createNotFoundException("Unknow qualif [$qualifId]");
        }
        $timeSlotId = $request->request->get('timeSlotId');
        $timeSlot = $mwsTimeSlotRepository->findOneBy([
            'id' => $timeSlotId,
        ]);
        if (!$timeSlot) {
            throw $this->createNotFoundException("Unknow time slot id [$timeSlotId]");
        }
        $wasQualified = count(array_intersect(
            $qualif->getTimeTags()->toArray(),
            $timeSlot->getTags()->toArray()
        )) == count($qualif->getTimeTags()->toArray());

        foreach ($mwsTimeQualifRepository->findAll() as $allQualif) {
            // Clean all existing tags (toggle)
            foreach ($allQualif->getTimeTags() as $tag) {
                $timeSlot->removeTag($tag);
            }
        }

        if (!$wasQualified) {
            // Add tag if was not present, keep clean otherwise
            foreach ($qualif->getTimeTags() as $tag) {
                $timeSlot->addTag($tag);
            }
        }

        $this->em->persist($timeSlot);
        $this->em->flush();

        return $this->json([
            'newTags' => $timeSlot->getTags(),
            'newCsrf' => $csrfTokenManager->getToken('mws-csrf-timing-qualif-toggle')->getValue(),
            'viewTemplate' => $viewTemplate,
        ]);
    }
}
