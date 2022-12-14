<?php

namespace Knuckles\Scribe\Tags2Attributes;


use Knuckles\Scribe\Tags2Attributes\TagParsers\ApiResourceTagParser;
use Knuckles\Scribe\Tags2Attributes\TagParsers\AuthTagParser;
use Knuckles\Scribe\Tags2Attributes\TagParsers\BodyParamTagParser;
use Knuckles\Scribe\Tags2Attributes\TagParsers\GroupTagParser;
use Knuckles\Scribe\Tags2Attributes\TagParsers\QueryParamTagParser;
use Knuckles\Scribe\Tags2Attributes\TagParsers\ResponseFieldTagParser;
use Knuckles\Scribe\Tags2Attributes\TagParsers\ResponseFileTagParser;
use Knuckles\Scribe\Tags2Attributes\TagParsers\ResponseTagParser;
use Knuckles\Scribe\Tags2Attributes\TagParsers\SubgroupTagParser;
use Knuckles\Scribe\Tags2Attributes\TagParsers\TransformerTagParser;
use Knuckles\Scribe\Tags2Attributes\TagParsers\UrlParamTagParser;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\PhpDocParser\Ast\Node as DocNode;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTagRemover;
use Rector\Core\Php\PhpVersionProvider;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\ValueObject\PhpVersionFeature;
use Rector\Naming\Naming\UseImportsResolver;
use Rector\Php80\NodeFactory\AttrGroupsFactory;
use Rector\Php80\NodeManipulator\AttributeGroupNamedArgumentManipulator;
use Rector\Php80\PhpDoc\PhpDocNodeFinder;
use Rector\Php80\ValueObject\AnnotationToAttribute;
use Rector\PhpAttribute\AttributeArrayNameInliner;
use Rector\PhpAttribute\NodeFactory\PhpAttributeGroupFactory;
use Rector\PhpAttribute\RemovableAnnotationAnalyzer;
use Rector\PhpAttribute\UnwrapableAnnotationAnalyzer;
use Rector\VersionBonding\Contract\MinPhpVersionInterface;
use RectorPrefix202208\Symplify\Astral\PhpDocParser\PhpDocNodeTraverser;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Most of the code here is copied from Rector's AnnotationToAttributeRector (Rector 0.14.0),
 * because it's a final class (can't be extended).
 * The changes:
 * - the `getArgs()` method and its usages
 * - creates its own config in the constructor
 * - handles some tags specially
 */
class RectorRule extends AbstractRector implements MinPhpVersionInterface
{
    protected array $annotationsToAttributes = [];

    public function __construct(
        protected PhpAttributeGroupFactory $phpAttributeGroupFactory,
        protected AttrGroupsFactory $attrGroupsFactory,
        protected PhpDocTagRemover $phpDocTagRemover,
        protected PhpDocNodeFinder $phpDocNodeFinder,
        protected UnwrapableAnnotationAnalyzer $unwrapableAnnotationAnalyzer,
        protected RemovableAnnotationAnalyzer $removableAnnotationAnalyzer,
        protected AttributeGroupNamedArgumentManipulator $attributeGroupNamedArgumentManipulator,
        protected PhpVersionProvider $phpVersionProvider,
        protected UseImportsResolver $useImportsResolver,
        protected AttributeArrayNameInliner $attributeArrayNameInliner
    )
    {
        $configuration = [
            new AnnotationToAttribute('header', \Knuckles\Scribe\Attributes\Header::class),
            new AnnotationToAttribute('urlParam', \Knuckles\Scribe\Attributes\UrlParam::class),
            new AnnotationToAttribute('urlparam', \Knuckles\Scribe\Attributes\UrlParam::class),
            new AnnotationToAttribute('queryParam', \Knuckles\Scribe\Attributes\QueryParam::class),
            new AnnotationToAttribute('queryparam', \Knuckles\Scribe\Attributes\QueryParam::class),
            new AnnotationToAttribute('bodyParam', \Knuckles\Scribe\Attributes\BodyParam::class),
            new AnnotationToAttribute('bodyparam', \Knuckles\Scribe\Attributes\BodyParam::class),
            new AnnotationToAttribute('responseField', \Knuckles\Scribe\Attributes\ResponseField::class),
            new AnnotationToAttribute('responsefield', \Knuckles\Scribe\Attributes\ResponseField::class),

            new AnnotationToAttribute('response', \Knuckles\Scribe\Attributes\Response::class),
            new AnnotationToAttribute('responseFile', \Knuckles\Scribe\Attributes\ResponseFromFile::class),
            new AnnotationToAttribute('responsefile', \Knuckles\Scribe\Attributes\ResponseFromFile::class),
            new AnnotationToAttribute('apiResource', \Knuckles\Scribe\Attributes\ResponseFromApiResource::class),
            new AnnotationToAttribute('apiresource', \Knuckles\Scribe\Attributes\ResponseFromApiResource::class),
            new AnnotationToAttribute('apiResourceCollection', \Knuckles\Scribe\Attributes\ResponseFromApiResource::class),
            new AnnotationToAttribute('apiresourcecollection', \Knuckles\Scribe\Attributes\ResponseFromApiResource::class),
            new AnnotationToAttribute('transformer', \Knuckles\Scribe\Attributes\ResponseFromTransformer::class),
            new AnnotationToAttribute('transformercollection', \Knuckles\Scribe\Attributes\ResponseFromTransformer::class),
            new AnnotationToAttribute('transformerCollection', \Knuckles\Scribe\Attributes\ResponseFromTransformer::class),
            new AnnotationToAttribute('subgroup', \Knuckles\Scribe\Attributes\Subgroup::class),

            // Only here for removal
            new AnnotationToAttribute('apiResourceModel', \Knuckles\Scribe\Attributes\ResponseFromApiResource::class),
            new AnnotationToAttribute('apiresourcemodel', \Knuckles\Scribe\Attributes\ResponseFromApiResource::class),
            new AnnotationToAttribute('apiresourceadditional', \Knuckles\Scribe\Attributes\ResponseFromApiResource::class),
            new AnnotationToAttribute('apiresourceAdditional', \Knuckles\Scribe\Attributes\ResponseFromApiResource::class),
            new AnnotationToAttribute('transformerModel', \Knuckles\Scribe\Attributes\ResponseFromTransformer::class),
            new AnnotationToAttribute('transformermodel', \Knuckles\Scribe\Attributes\ResponseFromTransformer::class),
            new AnnotationToAttribute('transformerPaginator', \Knuckles\Scribe\Attributes\ResponseFromTransformer::class),
            new AnnotationToAttribute('transformerpaginator', \Knuckles\Scribe\Attributes\ResponseFromTransformer::class),
            new AnnotationToAttribute('subgroupDescription', \Knuckles\Scribe\Attributes\Subgroup::class),
            new AnnotationToAttribute('subgroupdescription', \Knuckles\Scribe\Attributes\Subgroup::class),

        ];
        $this->annotationsToAttributes = $configuration;

        $this->unwrapableAnnotationAnalyzer->configure($configuration);
        $this->removableAnnotationAnalyzer->configure($configuration);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Convert Scribe docblock tags to attributes', ['']);
    }

    public function getNodeTypes(): array
    {
        return [
            Class_::class, ClassMethod::class,
        ];
    }

    public function refactor(Node $node): ?Node
    {
        $phpDocInfo = $this->phpDocInfoFactory->createFromNode($node);
        if (!$phpDocInfo instanceof PhpDocInfo) {
            return null;
        }
        $attributeGroups = $this->processTags($phpDocInfo);
        if ($attributeGroups === []) {
            return null;
        }
        $attributeGroups = $this->attributeGroupNamedArgumentManipulator->processSpecialClassTypes($attributeGroups);
        $node->attrGroups = \array_merge($node->attrGroups ?? [], $attributeGroups);
        return $node;
    }

    public function provideMinPhpVersion(): int
    {
        return PhpVersionFeature::ATTRIBUTES;
    }

    private function processTags(PhpDocInfo $phpDocInfo): array
    {
        $attributeGroups = [];
        $phpDocNodeTraverser = new PhpDocNodeTraverser();
        $phpDocNodeTraverser->traverseWithCallable($phpDocInfo->getPhpDocNode(), '', function (DocNode $docNode) use (&$attributeGroups, $phpDocInfo): ?int {
            if (!$docNode instanceof PhpDocTagNode) {
                return null;
            }

            // Replace auth tags
            if (in_array($docNode->name, ['@authenticated', '@unauthenticated'])) {
                $attributeGroups[] = $this->phpAttributeGroupFactory->createFromClassWithItems(
                    $docNode->name == '@authenticated'
                        ? \Knuckles\Scribe\Attributes\Authenticated::class
                        : \Knuckles\Scribe\Attributes\Unauthenticated::class,
                    [],
                );
                return PhpDocNodeTraverser::NODE_REMOVE;
            }

            if (!($docNode->value instanceof GenericTagValueNode)) {
                return null;
            }
            $tag = \trim($docNode->name, '@');
            // Handled by other tags, so just remove
            $removals = [
                'apiresourcemodel',
                'apiresourceadditional',
                'transformermodel',
                'transformerpaginator',
                'subgroupdescription',
            ];
            foreach ($this->annotationsToAttributes as $annotationToAttribute) {
                $desiredTag = $annotationToAttribute->getTag();
                if ($desiredTag !== $tag) {
                    continue;
                }
                if (in_array(strtolower($tag), $removals)) {
                    return PhpDocNodeTraverser::NODE_REMOVE;
                }

                $attributeClass = $annotationToAttribute->getAttributeClass();

                $items = $this->getArgs(
                    $tag, $docNode->value?->value, $phpDocInfo
                );
                $fullyQualified = new FullyQualified($attributeClass);
                $args = $this->phpAttributeGroupFactory->createArgsFromItems($items, $attributeClass);
                $args = $this->attributeArrayNameInliner->inlineArrayToArgs($args);
                $attribute = new Attribute($fullyQualified, $args);
                $attributeGroups[] = new AttributeGroup([$attribute]);

                $phpDocInfo->markAsChanged();
                return PhpDocNodeTraverser::NODE_REMOVE;
            }
            return null;
        });
        return $attributeGroups;
    }

    public function getArgs($tag, $tagContent, $phpDocInfo)
    {
        $tagContent = trim($tagContent);

        $parse = function ($class, ...$extraData) use ($tagContent) {
            $parsed = (new $class($tagContent, ...$extraData))->parse();
            $arguments = [];
            foreach ($parsed as $key => $value) {
                // Remove empty keys
                if ($value !== null && $value !== '') {
                    $arguments[$key] = $value;
                }
            }

            return $arguments;
        };

        $convertClassNamesToConst = fn(...$args) => self::convertClassNamesToConst(...$args);

        return match (strtolower($tag)) {
            'header' => explode(' ', $tagContent),
            'urlparam' => $parse(UrlParamTagParser::class),
            'queryparam' => $parse(QueryParamTagParser::class),
            'bodyparam' => $parse(BodyParamTagParser::class),
            'responsefield' => $parse(ResponseFieldTagParser::class),

            'response' => $parse(ResponseTagParser::class),
            'responsefile' => $parse(ResponseFileTagParser::class),
            'apiresource' => $convertClassNamesToConst($parse(ApiResourceTagParser::class, $phpDocInfo->getPhpDocNode()->getTags())),
            'apiresourcecollection' => $convertClassNamesToConst($parse(ApiResourceTagParser::class, $phpDocInfo->getPhpDocNode()->getTags(), true)),
            'transformer' => $convertClassNamesToConst($parse(TransformerTagParser::class, $phpDocInfo->getPhpDocNode()->getTags())),
            'transformercollection' => $convertClassNamesToConst($parse(TransformerTagParser::class, $phpDocInfo->getPhpDocNode()->getTags(), true)),

            'subgroup' => $parse(SubgroupTagParser::class, $phpDocInfo->getPhpDocNode()->getTags()),
        };
    }

    protected static function convertClassNamesToConst(array $args)
    {
        foreach ($args as $key => $value) {
            if (is_string($value) && class_exists($value)) {
                $fullyQualified = new FullyQualified($value);
                $args[$key] = new ClassConstFetch($fullyQualified, 'class');
            } else {
                $args[$key] = $value;
            }
        }

        return $args;
    }
}
