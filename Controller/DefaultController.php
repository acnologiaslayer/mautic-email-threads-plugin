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
        error_log('EmailThreads DefaultController instantiated');
    }
    public function indexAction(Request $request): Response
    {
        try {
            error_log('EmailThreads indexAction called');
            
            // Simple test - just return a basic response
            return new Response('<h1>Email Threads Plugin Works!</h1><p>Controller is accessible.</p>');
            
        } catch (\Exception $e) {
            error_log('EmailThreads Error: ' . $e->getMessage());
            return new Response('Error: ' . $e->getMessage());
        }
    }

    public function viewAction(Request $request, int $id): Response
    {
        // Check permissions - allow access if security is null (for testing)
        if ($this->security && !$this->security->isGranted('plugin:emailthreads:threads:view')) {
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
        // Check permissions - allow access if security is null (for testing)
        if ($this->security && !$this->security->isGranted('plugin:emailthreads:config:manage')) {
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
