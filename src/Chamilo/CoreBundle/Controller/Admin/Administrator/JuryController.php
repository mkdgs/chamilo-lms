<?php
/* For licensing terms, see /license.txt */

namespace Chamilo\CoreBundle\Controller\Admin\Administrator;

use Chamilo\CoreBundle\Controller\CrudController;
use Symfony\Component\Form\Extension\Validator\Constraints\FormValidator;
use Symfony\Component\HttpFoundation\Response;
use Entity;
use Chamilo\CoreBundle\Form\JuryType;
use Chamilo\CoreBundle\Form\JuryUserType;
use Chamilo\CoreBundle\Form\JuryMembersType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Chamilo\CoreBundle\Entity\JuryMembers;
use Chamilo\CoreBundle\Entity\Jury;

use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

/**
 * Class JuryController
 * @package Chamilo\CoreBundle\Controller\Admin\Administrator
 * @author Julio Montoya <gugli100@gmail.com>
 */
class JuryController
{
    public function getClass()
    {
        return 'Chamilo\CoreBundle\Entity\Jury';
    }

    public function getControllerAlias()
    {
        return 'jury.controller';
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return 'Chamilo\CoreBundle\Form\JuryType';
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplatePath()
    {
        return 'admin/administrator/juries/';
    }

    /**
    *
    * @Route("/{id}", requirements={"id" = "\d+"})
    * @Method({"GET"})
    */
    public function readAction($id)
    {
        $entity = $this->getEntity($id);

        $template = $this->get('template');
        $template->assign('item', $entity);
        $template->assign('links', $this->generateDefaultCrudRoutes());

        $response = $template->render_template($this->getTemplatePath().'read.tpl');
        return new Response($response, 200, array());
    }

     /**
    * @Route("/{id}/remove-member", requirements={"id" = "\d+"})
    * @Method({"GET"})
    */
    public function removeMemberAction($id)
    {
        $juryMembers = $this->getManager()->getRepository('Chamilo\CoreBundle\Entity\JuryMembers')->find($id);
        if ($juryMembers) {
            $em = $this->getManager();
            $em->remove($juryMembers);
            $em->flush();
            $this->get('session')->getFlashBag()->add('success', "Deleted");

            $url = $this->get('url_generator')->generate('jury.controller:readAction', array('id' => $juryMembers->getJuryId()));
            return $this->redirect($url);
        }
    }

    /**
    * @Route("/search-user/" )
    * @Method({"GET"})
    */
    public function searchUserAction()
    {
        $request = $this->getRequest();
        $keyword = $request->get('tag');

        $role = $request->get('role');
        /** @var \Chamilo\CoreBundle\Entity\Repository\UserRepository $repo */
        $repo = $this->getManager()->getRepository('Chamilo\UserBundle\Entity\User');

        if (empty($role)) {
            $entities = $repo->searchUserByKeyword($keyword);
        } else {
            $entities = $repo->searchUserByKeywordAndRole($keyword, $role);
        }

        $data = array();
        if ($entities) {
            /** @var \Chamilo\UserBundle\Entity\User $entity */
            foreach ($entities as $entity) {
                $data[] = array(
                    'key' => (string) $entity->getUserId(),
                    'value' => $entity->getCompleteName(),
                );
            }
        }
        return new JsonResponse($data);
    }

    /**
    * @Route("/{id}/add-members", requirements={"id" = "\d+"})
    * @Method({"GET"})
    */
    public function addMembersAction(Application $app, $id)
    {
        $juryUserType = new JuryMembersType();
        $juryMember =  new JuryMembers();
        $juryMember->setJuryId($id);
        $form = $this->createForm($juryUserType, $juryMember);
        $request = $this->getRequest();
        if ($request->getMethod() == 'POST') {
            $form->bind($request);
            if ($form->isValid()) {
                /** @var JuryMembers $item */
                $item = $form->getData();

                $userIdList = $item->getUserId();
                $userId = ($userIdList[0]);
                $user = $this->getManager()->getRepository('Chamilo\UserBundle\Entity\User')->find($userId);
                if (!$user) {
                    throw new \Exception('Unable to found User');
                }

                $jury = $this->getRepository()->find($id);

                if (!$jury) {
                    throw new \Exception('Unable to found Jury');
                }

                $juryMember->setUser($user);
                $juryMember->setJury($jury);

                $em = $this->getManager();
                $em->persist($juryMember);
                $em->flush();

                $this->get('session')->getFlashBag()->add('success', "Saved");
                $url = $this->get('url_generator')->generate('jury.controller:readAction', array('id' => $id));
                return $this->redirect($url);
            }
        }

        $template = $this->get('template');
        $template->assign('jury_id', $id);
        $template->assign('form', $form->createView());
        $response = $template->render_template($this->getTemplatePath().'add_members.tpl');
        return new Response($response, 200, array());
    }

    protected function generateDefaultCrudRoutes()
    {
        $routes = parent::generateDefaultCrudRoutes();
        $routes['add_members_link'] = 'jury.controller:addMembersAction';
        return $routes ;
    }


}
