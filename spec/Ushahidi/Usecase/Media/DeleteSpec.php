<?php

namespace spec\Ushahidi\Usecase\Media;

use Ushahidi\Tool\Authorizer;
use Ushahidi\Tool\Validator;
use Ushahidi\Entity\Media;
use Ushahidi\Usecase\Media\DeleteMediaRepository;
use Ushahidi\Usecase\Media\MediaDeleteData;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class DeleteSpec extends ObjectBehavior
{
	function let(DeleteMediaRepository $repo, Validator $valid, Authorizer $auth)
	{
		$this->beConstructedWith($repo, $valid, $auth);
	}

	function it_is_initializable()
	{
		$this->shouldHaveType('Ushahidi\Usecase\Media\Delete');
	}

	function it_fails_with_invalid_input($valid, MediaDeleteData $input)
	{
		$input->id = null;

		$valid->check($input)->willReturn(false);
		$valid->errors()->willReturn([]);
		$this->shouldThrow('Ushahidi\Exception\ValidatorException')->duringInteract($input);
	}

	function it_fails_when_media_does_not_exist($valid, $repo, MediaDeleteData $input, Media $media)
	{
		$input->id = 99999;
		$media->id = 0;

		$valid->check($input)->willReturn(true);

		$repo->get($input->id)->willReturn($media);

		$this->shouldThrow('Ushahidi\Exception\NotFoundException')->duringInteract($input);
	}

	function it_fails_when_not_allowed($valid, $repo, $auth, MediaDeleteData $input, Media $media)
	{
		$input->id = 1;
		$media->id = 1;

		$valid->check($input)->willReturn(true);

		$repo->get($input->id)->willReturn($media);

		$auth->isAllowed($media, 'delete')->willReturn(false);

		// Exception will contain the userid
		$auth->getUserId()->willReturn(1);
		$this->shouldThrow('Ushahidi\Exception\AuthorizerException')->duringInteract($input);
	}

	function it_can_delete_media_with_valid_input($valid, $repo, $auth, MediaDeleteData $input, Media $media)
	{
		$input->id = 1;
		$media->id = 1;

		$valid->check($input)->willReturn(true);

		$repo->get($input->id)->willReturn($media);

		$auth->isAllowed($media, 'delete')->willReturn(true);

		$repo->deleteMedia($input->id)->willReturn(1);

		$this->interact($input)->shouldReturnAnInstanceOf('Ushahidi\Entity\Media');
	}
}
