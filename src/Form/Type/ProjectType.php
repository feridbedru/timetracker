<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form\Type;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Repository\Query\ActivityQuery;
use App\Repository\Query\ProjectFormTypeQuery;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Custom form field type to select a project.
 */
class ProjectType extends AbstractType
{
    /**
     * @param Project $choiceValue
     * @param string $key
     * @param mixed $value
     * @return array
     */
    public function choiceAttr($choiceValue, $key, $value)
    {
        $customer = null;

        if (!($choiceValue instanceof Project)) {
            return [];
        }

        if (null !== $choiceValue->getCustomer()) {
            $customer = $choiceValue->getCustomer()->getId();
        }

        return ['data-customer' => $customer];
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // documentation is for NelmioApiDocBundle
            'documentation' => [
                'type' => 'integer',
                'description' => 'Project ID',
            ],
            'label' => 'label.project',
            'class' => Project::class,
            'choice_label' => 'name',
            'choice_attr' => [$this, 'choiceAttr'],
            'group_by' => function (Project $project, $key, $index) {
                return $project->getCustomer()->getName();
            },
            'query_builder_for_user' => true,
            'activity_enabled' => false,
            'activity_select' => 'activity',
            'activity_visibility' => ActivityQuery::SHOW_VISIBLE,
            'ignore_date' => false,
            'join_customer' => false,
            // @var Project|null
            'ignore_project' => null,
            // @var Customer|Customer[]|int|int[]|null
            'customers' => null,
            // @var Project|Project[]|int|int[]|null
            'projects' => null,
            // @var DateTime|null
            'project_date_start' => null,
            // @var DateTime|null
            'project_date_end' => null,
        ]);

        $resolver->setDefault('query_builder', function (Options $options) {
            return function (ProjectRepository $repo) use ($options) {
                $query = new ProjectFormTypeQuery($options['projects'], $options['customers']);
                if (true === $options['query_builder_for_user']) {
                    $query->setUser($options['user']);
                }

                if (true === $options['ignore_date']) {
                    $query->setIgnoreDate(true);
                } else {
                    if ($options['project_date_start'] !== null) {
                        $query->setProjectStart($options['project_date_start']);
                    }
                    if ($options['project_date_end'] !== null) {
                        $query->setProjectEnd($options['project_date_end']);
                    }
                }

                if (true === $options['join_customer']) {
                    $query->setWithCustomer(true);
                }

                if (null !== $options['ignore_project']) {
                    $query->setProjectToIgnore($options['ignore_project']);
                }

                return $repo->getQueryBuilderForFormType($query);
            };
        });

        $resolver->setDefault('api_data', function (Options $options) {
            if (false !== $options['activity_enabled']) {
                $name = \is_string($options['activity_enabled']) ? $options['activity_enabled'] : 'project';

                return [
                    'select' => $options['activity_select'],
                    'route' => 'get_activities',
                    'route_params' => [$name => '%' . $name . '%', 'visible' => $options['activity_visibility']],
                    'empty_route_params' => ['globals' => 'true', 'visible' => $options['activity_visibility']],
                ];
            }

            return [];
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return EntityType::class;
    }
}
