import { createContext, useContext } from 'react';

export type Language = 'ru' | 'en';

export interface LanguageContextType {
  language: Language;
  setLanguage: (lang: Language) => void;
  t: (text: any) => string;
}

export const LanguageContext = createContext<LanguageContextType>({
  language: 'ru',
  setLanguage: () => {},
  t: (text) => typeof text === 'string' ? text : text?.ru || text?.en || '',
});

export const useLanguage = () => useContext(LanguageContext);
