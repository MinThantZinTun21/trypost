# kotlinx.serialization keeps its generated serializers through its own
# consumer rules; Retrofit and OkHttp ship theirs too. The API interface is
# kept so Retrofit can read its annotations after shrinking.
-keep,allowobfuscation interface com.minthantzintun.trypost.core.network.MobileApi
