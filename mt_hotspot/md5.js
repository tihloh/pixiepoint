/* MD5 implementation used for MikroTik HTTP-CHAP authentication. */
(function exposeMd5(globalScope) {
  "use strict";

  function addWords(left, right) {
    var low = (left & 0xffff) + (right & 0xffff);
    var high = (left >>> 16) + (right >>> 16) + (low >>> 16);
    return (high << 16) | (low & 0xffff);
  }

  function rotateLeft(value, amount) {
    return (value << amount) | (value >>> (32 - amount));
  }

  function combine(result, current, next, message, shift, constant) {
    var sum = addWords(addWords(current, result), addWords(message, constant));
    return addWords(rotateLeft(sum, shift), next);
  }

  function roundF(current, next, third, fourth, message, shift, constant) {
    return combine(
      (next & third) | (~next & fourth),
      current,
      next,
      message,
      shift,
      constant
    );
  }

  function roundG(current, next, third, fourth, message, shift, constant) {
    return combine(
      (next & fourth) | (third & ~fourth),
      current,
      next,
      message,
      shift,
      constant
    );
  }

  function roundH(current, next, third, fourth, message, shift, constant) {
    return combine(
      next ^ third ^ fourth,
      current,
      next,
      message,
      shift,
      constant
    );
  }

  function roundI(current, next, third, fourth, message, shift, constant) {
    return combine(
      third ^ (next | ~fourth),
      current,
      next,
      message,
      shift,
      constant
    );
  }

  function wordToHex(value) {
    var output = "";
    var byteIndex;

    for (byteIndex = 0; byteIndex < 4; byteIndex += 1) {
      output += ("0" + ((value >>> (byteIndex * 8)) & 0xff).toString(16)).slice(-2);
    }

    return output;
  }

  globalScope.hexMD5 = function hexMD5(input) {
    var inputLength = input.length;
    var paddedInput = input + String.fromCharCode(128);
    var paddingLength = (56 - (paddedInput.length % 64) + 64) % 64;
    var lowBitLength = inputLength * 8;
    var highBitLength = Math.floor(inputLength / 536870912);
    var index;

    paddedInput += new Array(paddingLength + 1).join(String.fromCharCode(0));

    for (index = 0; index < 4; index += 1) {
      paddedInput += String.fromCharCode((lowBitLength >>> (index * 8)) & 0xff);
    }

    for (index = 0; index < 4; index += 1) {
      paddedInput += String.fromCharCode((highBitLength >>> (index * 8)) & 0xff);
    }

    var accumulatorA = 1732584193;
    var accumulatorB = -271733879;
    var accumulatorC = -1732584194;
    var accumulatorD = 271733878;
    var roundFunctions = [roundF, roundG, roundH, roundI];
    var shifts = [
      [7, 12, 17, 22],
      [5, 9, 14, 20],
      [4, 11, 16, 23],
      [6, 10, 15, 21]
    ];
    var constants = [];

    for (index = 0; index < 64; index += 1) {
      constants[index] = (Math.abs(Math.sin(index + 1)) * 4294967296) | 0;
    }

    var blockOffset;
    for (blockOffset = 0; blockOffset < paddedInput.length; blockOffset += 64) {
      var words = [];
      var savedA = accumulatorA;
      var savedB = accumulatorB;
      var savedC = accumulatorC;
      var savedD = accumulatorD;

      for (index = 0; index < 64; index += 4) {
        words[index >> 2] =
          paddedInput.charCodeAt(blockOffset + index) |
          (paddedInput.charCodeAt(blockOffset + index + 1) << 8) |
          (paddedInput.charCodeAt(blockOffset + index + 2) << 16) |
          (paddedInput.charCodeAt(blockOffset + index + 3) << 24);
      }

      for (index = 0; index < 64; index += 1) {
        var round = index >> 4;
        var wordIndex;

        if (round === 0) {
          wordIndex = index;
        } else if (round === 1) {
          wordIndex = ((5 * index) + 1) % 16;
        } else if (round === 2) {
          wordIndex = ((3 * index) + 5) % 16;
        } else {
          wordIndex = (7 * index) % 16;
        }

        var nextB = roundFunctions[round](
          accumulatorA,
          accumulatorB,
          accumulatorC,
          accumulatorD,
          words[wordIndex],
          shifts[round][index % 4],
          constants[index]
        );
        accumulatorA = accumulatorD;
        accumulatorD = accumulatorC;
        accumulatorC = accumulatorB;
        accumulatorB = nextB;
      }

      accumulatorA = addWords(accumulatorA, savedA);
      accumulatorB = addWords(accumulatorB, savedB);
      accumulatorC = addWords(accumulatorC, savedC);
      accumulatorD = addWords(accumulatorD, savedD);
    }

    return (
      wordToHex(accumulatorA) +
      wordToHex(accumulatorB) +
      wordToHex(accumulatorC) +
      wordToHex(accumulatorD)
    );
  };
}(this));
