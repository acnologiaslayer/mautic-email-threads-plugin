<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailThreadsBundle\Controller;

use Mautic\CoreBundle\Controller\AbstractStandardFormController;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThread;
use MauticPlugin\MauticEmailThreadsBundle\Model\EmailThreadModel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DefaultController extends AbstractStandardFormController
{
    /**
     * Returns the model service name for this controller.
     */
    protected function getModelName(): string
    {
        return 'mautic.emailthreads.model.thread';
    }
    public function __construct(
        private EmailThreadModel $threadModel,
        protected CoreParametersHelper $coreParametersHelper,
        protected ?CorePermissions $security
    ) {
    }
    public function indexAction(Request $request): Response
    {
        // Check permissions
        if (!$this->security?->isGranted('plugin:emailthreads:threads:view')) {
            throw new AccessDeniedException();
        }

        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 30);
        
        $threads = $this->threadModel->findActiveThreads();
        
        return $this->render('@MauticEmailThreads/Default/index.html.twig', [
            'threads' => $threads,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    public function viewAction(Request $request, int $id): Response
    {
        // Check permissions
        if (!$this->security?->isGranted('plugin:emailthreads:threads:view')) {
            throw new AccessDeniedException();
        }

        $thread = $this->threadModel->getEntity($id);
        
        if (!$thread) {
            throw $this->createNotFoundException('Thread not found');
        }

        return $this->render('@MauticEmailThreads/Default/view.html.twig', [
            'thread' => $thread,
        ]);
    }

    public function configAction(Request $request): Response
    {
        // Check permissions
        if (!$this->security?->isGranted('plugin:emailthreads:config:manage')) {
            throw new AccessDeniedException();
        }

        $form = $this->createForm(
            \MauticPlugin\MauticEmailThreadsBundle\Form\Type\ConfigType::class,
            [],
            ['action' => $this->generateUrl('mautic_emailthreads_config')]
        );

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            
            if ($form->isValid()) {
                $data = $form->getData();
                
                // Save configuration
                foreach ($data as $key => $value) {
                    $this->coreParametersHelper->set($key, $value);
                }
                
                $this->addFlash('notice', 'Configuration updated successfully');
                
                return $this->redirectToRoute('mautic_emailthreads_config');
            }
        }

        return $this->render('@MauticEmailThreads/Config/form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function cleanupAction(Request $request): JsonResponse
    {
        // Check permissions
        if (!$this->security?->isGranted('plugin:emailthreads:config:manage')) {
            return new JsonResponse(['success' => false, 'message' => 'Access denied'], 403);
        }

        try {
            $daysOld = $request->query->getInt('days', 30);
            
            $deactivatedCount = $this->threadModel->deactivateExpiredThreads($daysOld);
            
            return new JsonResponse([
                'success' => true,
                'message' => "Deactivated {$deactivatedCount} expired threads",
                'count' => $deactivatedCount,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error during cleanup: ' . $e->getMessage(),
            ], 500);
        }
    }
}
