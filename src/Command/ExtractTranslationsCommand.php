<?php
namespace Concrete\Package\TranslationUtilities\Src\Command;

use Config;
use Core;
use Gettext\Translations;
use Gettext\Generators\Po as PoGenerator;
use Package;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Bridge\Twig\Form\TwigRenderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\DefaultCsrfProvider;
use Symfony\Bridge\Twig\Extension\FormExtension;

class ExtractTranslationsCommand extends Command
{

    protected function configure()
    {
        $this
            ->setName('translations:extract_translations')
            ->setDescription('Extracts all the translations from the package and creates the messages.pot file')
            ->addArgument(
                'package',
                InputArgument::REQUIRED,
                'Which package you want to extract the translations from?'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $pkg = Package::getByHandle($input->getArgument('package'));
        if (!is_object($pkg)) {
            throw new \Exception(t("Invalid package!"));
        }

        $translations = new Translations();

        $extractedFrom = array();
        if ($this->parsePhpTranslations($pkg, $translations)) {
            $extractedFrom[] = 'php';
        }
        if ($this->parseTwigTranslations($pkg, $translations)) {
            $extractedFrom[] = 'twig';
        }

        $dir = $pkg->getPackagePath() . '/' . DIRNAME_LANGUAGES;
        if (!file_exists($dir)) {
            if (@mkdir($dir, Config::get('concrete.filesystem.permissions.directory')) === false) {
                throw new \Exception(t("Could not generate the languages directory. Please check file permissions!"));
            }
        }

        $pot = $dir . '/messages.pot';
        PoGenerator::toFile($translations, $pot);

        $output->writeln(sprintf("Translations extracted. Extracted from: %s.", implode(', ', $extractedFrom)));
    }

    protected function parsePhpTranslations(Package $pkg, Translations $translations)
    {
        // Check /concrete/src/Multilingual/Service/Extractor
        $parser = new \C5TL\Parser\Php();
        $parser->parseDirectory(
            $pkg->getPackagePath(),
            $pkg->getRelativePath(),
            $translations
        );

        return true;
    }

    protected function parseTwigTranslations(Package $pkg, Translations $translations)
    {
        if (!Core::bound($pkg->getPackageHandle() . '/environment/twig')) {
            return false;
        }
        $dirs = array(
            $pkg->getPackagePath() . '/' . DIRNAME_PAGES,
            $pkg->getPackagePath() . '/' . DIRNAME_VIEWS,
        );
        $twig = Core::make($pkg->getPackageHandle() . '/environment/twig');

        $secret = md5(Config::get('concrete.misc.access_entity_updated') . Config::get('concrete.version_installed') . __FILE__);
        $csrfProvider = new DefaultCsrfProvider($secret);

        $formEngine = new TwigRendererEngine(array('form_concrete_layout.html.twig'));
        $formEngine->setEnvironment($twig);
        $twig->addExtension(new FormExtension(
            new TwigRenderer($formEngine, $csrfProvider))
        );

        $sourceLocale = Config::get('concrete.multilingual.default_source_locale');
        $extractor = new \Symfony\Bridge\Twig\Translation\TwigExtractor($twig);
        $catalogue = new \Symfony\Component\Translation\MessageCatalogue($sourceLocale);

        foreach ($dirs as $dir) {
            $extractor->extract($dir, $catalogue);
        }

        $strings = $catalogue->all();

        $defaultContext = 'messages';
        $selector = new \Symfony\Component\Translation\MessageSelector();
        foreach ($strings as $context => $messages) {
            $ctx = $context == $defaultContext ? '' : $context;
            foreach ($messages as $msg) {
                $single = $selector->choose($msg, 1, $sourceLocale);
                $plural = $selector->choose($msg, 2, $sourceLocale);
                if ($single == $plural) {
                    $plural = '';
                }
                $translations->insert($ctx, $single, $plural);
            }
        }

        return true;
    }

}