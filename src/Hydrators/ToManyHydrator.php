<?php
/*
 * Copyright 2021 Cloud Creativity Limited
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Hydrators;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use LaravelJsonApi\Contracts\Store\ToManyBuilder;
use LaravelJsonApi\Core\Query\Custom\ExtendedQueryParameters;
use LaravelJsonApi\Core\Support\Str;
use LaravelJsonApi\Eloquent\Contracts\FillableToMany;
use LaravelJsonApi\Eloquent\Fields\Relations\ToMany;
use LaravelJsonApi\Eloquent\HasQueryParameters;
use LaravelJsonApi\Eloquent\Polymorphism\MorphMany;
use LaravelJsonApi\Eloquent\Schema;
use UnexpectedValueException;

class ToManyHydrator implements ToManyBuilder
{

    use HasQueryParameters;

    /**
     * @var Schema
     */
    private Schema $schema;

    /**
     * @var Model
     */
    private Model $model;

    /**
     * @var ToMany|FillableToMany
     */
    private ToMany $relation;

    /**
     * ToManyHydrator constructor.
     *
     * @param Schema $schema
     * @param Model $model
     * @param ToMany $relation
     */
    public function __construct(Schema $schema, Model $model, ToMany $relation)
    {
        if (!$relation instanceof FillableToMany) {
            throw new UnexpectedValueException(sprintf(
                'Relation %s cannot be hydrated.',
                Str::dasherize(class_basename($relation))
            ));
        }

        $this->schema = $schema;
        $this->model = $model;
        $this->relation = $relation;
        $this->queryParameters = new ExtendedQueryParameters();
    }

    /**
     * @inheritDoc
     */
    public function sync(array $identifiers): iterable
    {
        $related = $this->model->getConnection()->transaction(
            fn() => $this->relation->sync($this->model, $identifiers)
        );

        $this->prepareModel();

        return $this->relation->parse(
            $this->prepareResult($related)
        );
    }

    /**
     * @inheritDoc
     */
    public function attach(array $identifiers): iterable
    {
        $related = $this->model->getConnection()->transaction(
            fn() => $this->relation->attach($this->model, $identifiers)
        );

        $this->prepareModel();

        return $this->relation->parse(
            $this->prepareResult($related)
        );
    }

    /**
     * @inheritDoc
     */
    public function detach(array $identifiers): iterable
    {
        $related = $this->model->getConnection()->transaction(
            fn() => $this->relation->detach($this->model, $identifiers)
        );

        $this->prepareModel();

        return $this->relation->parse(
            $this->prepareResult($related)
        );
    }

    /**
     * Prepare the result for returning.
     *
     * @param EloquentCollection|MorphMany $related
     * @return iterable
     */
    private function prepareResult(iterable $related): iterable
    {
        /** Always do eager loading, in case we have default include paths. */
        if ($related instanceof EloquentCollection && $related->isNotEmpty()) {
            $this->relation->schema()->loaderFor($related)->loadMissing(
                $this->queryParameters->includePaths()
            );
        }

        if ($related instanceof MorphMany) {
            $related->loadMissing(
                $this->queryParameters->includePaths()
            );
        }

        return $related;
    }

    /**
     * @return $this
     */
    private function prepareModel(): self
    {
        if ($this->relation->isCountableInRelationship()) {
            $this->schema->loaderFor($this->model)->loadCount(
                $this->relation->name(),
            );
        }

        return $this;
    }

}
