<?php declare(strict_types = 1);

namespace Vairogs\Sitemap\Command;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vairogs\Sitemap\Builder\Director;
use Vairogs\Sitemap\Builder\FileBuilder;
use Vairogs\Sitemap\Provider;
use function fclose;
use function getcwd;
use function sprintf;
use function unlink;

class SitemapCommand extends Command
{
    protected static $defaultName = 'vairogs:sitemap';

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var array
     */
    private $options;

    /**
     * @var Provider|null
     */
    private $provider;

    /**
     * @param ValidatorInterface $validator
     * @param Provider|null $provider
     * @param array $options
     */
    public function __construct(ValidatorInterface $validator, ?Provider $provider = null, array $options = [])
    {
        if (null === $provider || (false === $options['enabled'])) {
            throw new NotFoundHttpException('To use VairogsSitemap, you must enable it and provide a Provider');
        }

        $this->validator = $validator;
        $this->options = $options;
        $this->provider = $provider;
        parent::__construct(self::$defaultName);
    }

    protected function configure(): void
    {
        $host = $this->options['host'] ?? null;
        $this->setDescription('Regenerate sitemap.xml')
            ->addArgument('host', $host ? InputArgument::OPTIONAL : InputArgument::REQUIRED, 'host to use in sitemap', $this->options['host'])
            ->addOption('filename', null, InputOption::VALUE_OPTIONAL, 'sitemap filename if not sitemap.xml', 'sitemap.xml');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $sitemap = $this->provider->populate($input->getArgument('host'));
        $errors = $this->validator->validate($sitemap);
        if ($errors->count()) {
            foreach ($errors as $error) {
                /** @var ConstraintViolation $error */
                $output->writeln($error->getMessage());
            }
        } else {
            $output->writeln('<fg=blue>Generating sitemap</>');
            $filename = getcwd() . '/public/' . $input->getOption('filename');

            @unlink($filename);
            $handle = fopen($filename, 'w+b');
            try {
                (new Director($handle))->build(new FileBuilder($sitemap));
                $output->writeln(sprintf('<info>Sitemap generated as "%s"</info>', $filename));
            } catch (Exception $e) {
                @unlink($filename);
                $output->writeln('<error>' . $e->getMessage() . '</error>');
            }
            fclose($handle);
        }
    }
}