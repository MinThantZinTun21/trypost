package com.minthantzintun.trypost.core.di

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.preferencesDataStore
import com.minthantzintun.trypost.core.session.DataStoreSessionStore
import com.minthantzintun.trypost.core.session.KeystoreSecretCipher
import com.minthantzintun.trypost.core.session.SecretCipher
import com.minthantzintun.trypost.core.session.SessionStore
import dagger.Binds
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import kotlinx.serialization.json.Json
import okhttp3.OkHttpClient
import java.util.concurrent.TimeUnit
import javax.inject.Singleton

private val Context.sessionDataStore: DataStore<Preferences> by preferencesDataStore(name = "session")

@Module
@InstallIn(SingletonComponent::class)
object AppModule {
    @Provides
    @Singleton
    fun provideJson(): Json = Json {
        ignoreUnknownKeys = true
        explicitNulls = false
        coerceInputValues = true
    }

    @Provides
    @Singleton
    fun provideOkHttpClient(): OkHttpClient = OkHttpClient.Builder()
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .build()

    @Provides
    @Singleton
    fun provideSessionDataStore(@ApplicationContext context: Context): DataStore<Preferences> = context.sessionDataStore
}

@Module
@InstallIn(SingletonComponent::class)
abstract class SessionModule {
    @Binds
    abstract fun bindSessionStore(store: DataStoreSessionStore): SessionStore

    @Binds
    abstract fun bindSecretCipher(cipher: KeystoreSecretCipher): SecretCipher
}
