<?php

return function (PHPCensor\Plugin\Util\Factory $factory) {
    $factory->registerResource(
        // This function will be called when the resource is needed.
        function() {
            return [
                'Foo' => "Stuff",
                'Bar' => "More Stuff"
            ];
        },

        // In addition to the function for building the resource the system
        // also needs to be told when to load the resource. Either or both
        // of the following arguments can be used (null to ignore)

        // This resource will only be given when the argument name is:
        "ResourceArray",

        // The resource will only be given when the type hint is:
        PHPCensor\Plugin\Util\Factory::TYPE_ARRAY
    );
};