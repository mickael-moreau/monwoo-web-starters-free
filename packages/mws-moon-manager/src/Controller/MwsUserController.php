<?php
// 🌖🌖 Copyright Monwoo 2023 🌖🌖, build by Miguel Monwoo, service@monwoo.com

namespace MWS\MoonManagerBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use MWS\MoonManagerBundle\Form\MwsUserFilterType;
use MWS\MoonManagerBundle\Repository\MwsUserRepository;
use MWS\MoonManagerBundle\Security\MwsLoginFormAuthenticator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/{_locale<%app.supported_locales%>}/mws-user')]
class MwsUserController extends AbstractController
{
    public static $userRolesByFilterTag = [
        'telepro' => [ 'ROLE_TELEPRO' ],
        'confirmateur' => [ 'ROLE_TELEPRO',  'ROLE_CONFIRMATEUR' ],
        'commercial' => [ 'ROLE_COMMERCIAL' ],
        'resp-commercial' => [ 'ROLE_COMMERCIAL', 'ROLE_RESP_COMMERCIAL' ],
    ];

    public function __construct(
        protected LoggerInterface $logger,
        protected SerializerInterface $serializer,
        protected TranslatorInterface $translator,
        protected EntityManagerInterface $em
    ){
    }

    #[Route('/',
        name: 'mws_user',
        options: ['expose' => true],
    )]
    public function index(Request $request): Response
    {
        // TODO : depending of roles : will show user profile or list of editable profiles
        return $this->redirectToRoute(
            'mws_user_list',
            array_merge($request->query->all(), [
                // "filterTags" => $filterTags,
                // "keyword" => $keyword
            ]),
            Response::HTTP_SEE_OTHER
        );
    }

    #[Route('/list/{filterTags}',
        name: 'mws_user_list',
        methods: ['GET', 'POST'],
        defaults: [
            'filterTags' => null,
        ],
    )]

    public function list(
        string|null $filterTags,
        MwsUserRepository $mwsUserRepository,
        PaginatorInterface $paginator,
        Request $request,
    ): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('mws_user_login');
        
            // return $this->json([
            //     "status" => "ko",
            //     "comment" => "Nop, rien ici",
            // ], 401);
        }

        $filterTagsTokens = explode("_", $filterTags);
        $userRolesByTag = array_reduce($filterTagsTokens, function($acc, $filterTag) {
            $acc[] = MwsUserController::$userRolesByFilterTag[$filterTag] ?? null;
            return $acc;
        }, []);

        $userRolesByTag = array_unique($userRolesByTag);

        $keyword = $request->query->get('keyword', null);

        $qb = $mwsUserRepository->createQueryBuilder('u');
        // USE CUSTOM form filters instead of knp simple ones

        $lastSearch = [
            "jsonResult" => json_encode([
                "searchKeyword" => $keyword,
            ]),
            "surveyJsModel" => $this->renderView(
                "@MoonManager/mws-user/survey-js-models/MwsUserFilterType.json.twig"
            ),
        ]; // TODO : save in session or similar ? or keep GET system data transfert system ?
        $filterForm = $this->createForm(MwsUserFilterType::class, $lastSearch);
        $filterForm->handleRequest($request);

        if ($filterForm->isSubmitted()) {
            $this->logger->debug("Did to submit search form");

            if ($filterForm->isValid()) {
                $this->logger->debug("Search form ok");
                // dd($filterForm);

                $surveyAnswers = json_decode(
                    $filterForm->get('jsonResult')->getData(), true
                );
                $keyword = $surveyAnswers['searchKeyword'] ?? null;
                return $this->redirectToRoute(
                    'mws_user_list',
                    array_merge($request->query->all(), [
                        "filterTags" => $filterTags,
                        "keyword" => $keyword
                    ]),
                    Response::HTTP_SEE_OTHER
                );
            }
        }

        if ($keyword) {
            $qb
            ->andWhere("
                LOWER(REPLACE(u.username, ' ', '')) LIKE LOWER(REPLACE(:keyword, ' ', ''))
                OR LOWER(REPLACE(u.email, ' ', '')) LIKE LOWER(REPLACE(:keyword, ' ', ''))
            ")
            ->setParameter('keyword', '%' . $keyword . '%');
        }
        $qb->select("
            u,
            LOWER(REPLACE(u.username, ' ', '')) AS username
        ");
        $extraFilter = $request->query->get('extraFilter', null);
        $expr = $qb->expr();

        // if ("xxx" === $extraFilter) {
        //     $qb->andWhere(
        //         "
        //         (NOT (u.xxx = 1))
        //         OR u.xxx IS NULL
        //         "
        //     );
        // } else if ("yyy" === $extraFilter) {
        //     $qb->andWhere(
        //         $expr->eq('u.yyy', 1)
        //     );
        // }

        if ($filterTags) {
            foreach($userRolesByTag ?? [] as $idx => $role) {
                $qb->AndWhere("u.roles LIKE :role$idx ")
                ->setParameter("role$idx", "%$role%");
            }
        }

        $query = $qb->getQuery();
        // dd($query->getResult());    
        $pagination = $paginator->paginate(
            $query, /* query NOT result */
            $request->query->getInt('page', 1), /*page number*/
            10 /*limit per page*/
        );

        $this->logger->debug("Succeed to list users");
        // return $this->renderForm('@MoonManager/mws-user/list.html.twig', [ // TODO : depreciated
        return $this->render('@MoonManager/mws-user/list.html.twig', [
            'title' => 'Utilisateurs',
            'filterForm' => $filterForm,
            'pagination' => $pagination,
            'filterTags' => $filterTags,
            // TODO : 'availableRoles' => $mwsUserRepository->getAvailableRoles(),
            // 'currentStatusId' => $currentStatusId,
        ]);
    }


    #[Route('/login',
        name: 'mws_user_login',
        options: ['expose' => true],
    )]
    public function login(
        Request $request,
        AuthenticationUtils $authenticationUtils
    ): Response {
        // $this->translator->trans('MwsLoginFormAuthenticator.accessDenied', [], 'mws-moon-manager');

        // new FlashBag();
        $flashBag = $request->getSession()->getFlashBag();
        if ($this->getUser() && !count($flashBag->keys())) {
            return $this->redirectToRoute(MwsLoginFormAuthenticator::SUCCESS_LOGIN_ROUTE);
        }

        // get the login error if there is one
        // TODO : no error on csrf token error ?
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();
        return $this->render('@MoonManager/mws-user/login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    #[Route('/logout',
        name: 'mws_user_logout',
        options: ['expose' => true],
    )]
    public function logout(): Response
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

}
