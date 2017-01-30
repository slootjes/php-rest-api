<?php

namespace MediaMonks\RestApi\EventSubscriber;

use MediaMonks\RestApi\Model\ResponseModelFactory;
use MediaMonks\RestApi\Request\RequestMatcherInterface;
use MediaMonks\RestApi\Request\RequestTransformerInterface;
use MediaMonks\RestApi\Response\Response as RestApiResponse;
use MediaMonks\RestApi\Response\ResponseTransformerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class IOEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var RequestMatcherInterface
     */
    private $requestMatcher;

    /**
     * @var RequestTransformerInterface
     */
    private $requestTransformer;

    /**
     * @var ResponseTransformerInterface
     */
    private $responseTransformer;

    /**
     * @param RequestMatcherInterface $requestMatcher
     * @param RequestTransformerInterface $requestTransformer
     * @param ResponseTransformerInterface $responseTransformer
     */
    public function __construct(
        RequestMatcherInterface $requestMatcher,
        RequestTransformerInterface $requestTransformer,
        ResponseTransformerInterface $responseTransformer
    ) {
        $this->requestMatcher = $requestMatcher;
        $this->requestTransformer = $requestTransformer;
        $this->responseTransformer = $responseTransformer;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST   => [
                ['onRequest', 512],
            ],
            KernelEvents::EXCEPTION => [
                ['onException', 512],
            ],
            KernelEvents::VIEW      => [
                ['onView', 0],
            ],
            KernelEvents::RESPONSE  => [
                ['onResponseEarly', 0],
                ['onResponseLate', -512],
            ],
        ];
    }

    /**
     * @param GetResponseEvent $event
     */
    public function onRequest(GetResponseEvent $event)
    {
        if (!$this->eventRequestMatches($event)) {
            return;
        }
        $this->requestTransformer->transform($event->getRequest());
    }

    /**
     * convert exception to rest api response
     *
     * @param GetResponseForExceptionEvent $event
     */
    public function onException(GetResponseForExceptionEvent $event)
    {
        if (!$this->eventRequestMatches($event)) {
            return;
        }
        $event->setResponse($this->responseTransformer->createResponseFromContent($event->getException()));
    }

    /**
     * convert response to rest api response
     *
     * @param GetResponseForControllerResultEvent $event
     */
    public function onView(GetResponseForControllerResultEvent $event)
    {
        if (!$this->eventRequestMatches($event)) {
            return;
        }
        $event->setResponse($this->responseTransformer->createResponseFromContent($event->getControllerResult()));
    }

    /**
     * converts content to correct output format
     *
     * @param FilterResponseEvent $event
     */
    public function onResponseEarly(FilterResponseEvent $event)
    {
        if (!$this->eventRequestMatches($event)) {
            return;
        }
        $event->setResponse($this->responseTransformer->transformEarly($event->getRequest(), $event->getResponse()));
    }

    /**
     * wrap the content if needed
     *
     * @param FilterResponseEvent $event
     */
    public function onResponseLate(FilterResponseEvent $event)
    {
        if (!$this->eventRequestMatches($event)) {
            return;
        }
        $this->responseTransformer->transformLate($event->getRequest(), $event->getResponse());
    }

    /**
     * @param KernelEvent $event
     * @return bool
     */
    protected function eventRequestMatches(KernelEvent $event)
    {
        return $this->requestMatcher->matches($event->getRequest(), $event->getRequestType());
    }

}
