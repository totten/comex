<?php
use \Symfony\Component\Console\Output\OutputInterface;

/**
 * Set the releaseDate in info.xml (which will then be bundled into the zip files).
 *
 * NOTE: In practice, this is more like the *build date* than the *release date*.
 * Those should *generally* be the same -- especially with an automated build/publish
 * pipeline.
 *
 * What about the edge-cases where they differ? IMHO, it's design-flaw to put
 * "release date" into the zip file -- that is extrinsic to the build-artifact,
 * and it's not completely knowable until *after* the build. By contrast,
 * the "build date" is intrinsic to the zip file, so it can be accurately put in.
 */
return function(OutputInterface $output, SimpleXMLElement $infoXml, &$composerJson) {
  // TODO: if we're in a git repo, consider using the timestamp from the tag or last-commit.
  // That would provide greater reproducibility.
  $infoXml->releaseDate = date('Y-m-d');
};
