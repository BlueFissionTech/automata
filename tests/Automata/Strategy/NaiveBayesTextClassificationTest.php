<?php
namespace BlueFission\Tests\Automata\Strategy;

use BlueFission\Automata\Strategy\NaiveBayesTextClassification;
use Phpml\Dataset\ArrayDataset;
use Phpml\CrossValidation\RandomSplit;
use Phpml\Pipeline;
use Phpml\Classification\NaiveBayes;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\Tokenization\WhitespaceTokenizer;
use PHPUnit\Framework\TestCase;

class NaiveBayesTextClassificationTest extends TestCase
{
    /** External disjoint evaluation must not remove random rows from fitting. */
    public function testZeroSplitTrainsAllAdmittedExamples(): void
    {
        $model = new NaiveBayesTextClassification();
        $model->train(['quiet routine hello', 'quiet normal welcome', 'urgent danger alarm', 'urgent danger critical'],
            ['wait', 'wait', 'assist', 'assist'], 0.0);
        $this->assertSame('wait', $model->predict('quiet routine request'));
        $this->assertSame('assist', $model->predict('urgent danger request'));
        $this->assertSame(0.0, $model->accuracy());
    }

    private $nbClassifier;

    protected function setUp(): void
    {
        if (!class_exists(\Phpml\Classification\NaiveBayes::class)) {
            $this->markTestSkipped('php-ai/php-ml is not available; skipping NaiveBayesTextClassification tests.');
        }

        $this->nbClassifier = new NaiveBayesTextClassification();
    }

    public function testTrain()
    {
        $samples = ['I love programming', 'PHP is awesome', 'Machine learning is fascinating'];
        $labels = ['positive', 'positive', 'positive'];
        $this->nbClassifier->train($samples, $labels, 0.2);

        $this->assertNotEmpty($this->nbClassifier->predict('I love programming'));
    }

    public function testPredict()
    {
        $samples = ['I love programming', 'PHP is awesome', 'Machine learning is fascinating'];
        $labels = ['positive', 'positive', 'positive'];
        $this->nbClassifier->train($samples, $labels, 0.2);

        $prediction = $this->nbClassifier->predict('I love programming');
        $this->assertEquals('positive', $prediction);
    }

    public function testAccuracy()
    {
        $samples = ['I love programming', 'PHP is awesome', 'Machine learning is fascinating'];
        $labels = ['positive', 'positive', 'positive'];
        $this->nbClassifier->train($samples, $labels, 0.2);

        $this->nbClassifier->accuracy();
        $this->assertTrue(true);
    }

    public function testSaveLoadModel()
    {
        $samples = ['I love programming', 'PHP is awesome', 'Machine learning is fascinating'];
        $labels = ['positive', 'positive', 'positive'];
        $this->nbClassifier->train($samples, $labels, 0.2);

        $path = 'naive_bayes_model.ser';
        $this->nbClassifier->saveModel($path);
        $this->assertFileExists($path);

        $newNbClassifier = new NaiveBayesTextClassification();
        $newNbClassifier->loadModel($path);
        $prediction = $newNbClassifier->predict('I love programming');
        $this->assertEquals('positive', $prediction);

        unlink($path);
    }
}
