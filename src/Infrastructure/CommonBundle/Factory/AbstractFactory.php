<?php

namespace Leos\Infrastructure\CommonBundle\Factory;

use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormInterface;

use Leos\Infrastructure\CommonBundle\Exception\Form\FormException;
use Leos\Infrastructure\CommonBundle\Exception\Form\FormFactoryException;

/**
 * Class AbstractFactory
 *
 * @package Leos\Infrastructure\CommonBundle\Factory
 */
abstract class AbstractFactory
{
    const
        CREATE = 'POST',
        REPLACE = 'PUT',
        UPDATE = 'PATCH'
    ;

    /**
     * @var FormFactory
     */
    private $formFactory;

    /**
     * @var string
     */
    protected $formClass;

    public function __construct(FormFactory $formFactory)
    {
        $this->formFactory = $formFactory;
        
        if (!$this->formClass) {
            
            throw new FormFactoryException();
        }
    }

    /**
     * @param string $action
     * @param array $data
     * @param null|object $object
     *
     * @return mixed
     *
     * @throws FormException
     */
    protected function execute(string $action = self::CREATE, array $data, $object = null)
    {
        $form = $this->createForm($action, $object)->submit($data, self::UPDATE !== $action);

        if (!$form->isValid()) {

            throw new FormException($form);
        }

        if (is_object($object)
            && in_array($action, [self::UPDATE, self::REPLACE])
            && method_exists($object, 'setUpdate')) {

            $object->setUpdate(new \DateTimeImmutable());
        }

        return $form->getData();
    }

    /**
     * @param string $action
     * @param null|object $object
     *
     * @return FormInterface
     */
    private function createForm(string $action = self::CREATE, $object = null): FormInterface
    {
        return $this->formFactory->create($this->formClass, $object, [
            'method' => $action
        ]);
    }
}
