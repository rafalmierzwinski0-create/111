"""Word lists for the heuristic scorer, per transcript language (en, pl).

Unknown languages fall back to English lists. Only the density and
duration/energy features are language independent.
"""

from __future__ import annotations

EN = {
    "stopwords": set("""a an the and or but so because if then than that this these those it its it's is are was were be been
        being am do does did doing have has had having i me my mine we us our you your yours he him his she her they them
        their what which who whom whose when where why how all any both each few more most other some such no nor not only
        own same too very can will just should now of at by for with about against between into through during before after
        above below to from up down in out on off over under again further once here there also yeah yes oh okay ok like
        um uh well really right get got going go know think mean say said thing things kind sort lot actually""".split()),
    "fillers": ["um", "uh", "erm", "hmm", "like", "you know", "i mean", "sort of", "kind of", "basically", "literally",
                "you know what i mean", "whatever"],
    "backref_first": {"and", "but", "so", "or", "because", "also", "plus", "then", "which", "although", "though"},
    "pronoun_first": {"it", "that", "they", "he", "she", "this", "those", "these", "them", "there"},
    "response_first": {"yeah", "yes", "yep", "right", "exactly", "totally", "sure", "no", "nope", "absolutely", "true",
                       "agreed", "definitely", "okay", "ok", "mhm", "uh-huh"},
    "backref_phrases": ["like i said", "as i said", "as i mentioned", "like you said", "as you said", "going back to",
                        "that's why", "that is why", "which is why", "on that note"],
    "hooks": ["the biggest", "the most", "most people", "nobody", "no one", "never", "always", "the secret", "the truth",
              "the problem", "the mistake", "mistake", "here's", "here is", "the reason", "the one thing", "what if",
              "imagine", "the real", "nobody tells you", "wrong", "myth", "surprising", "shocking", "counterintuitive",
              "the hardest", "the best", "the worst", "stop", "why do", "why does", "how do you", "what happens"],
    "conclusions": ["so the lesson", "the lesson", "that's why", "that is why", "which means", "the point is",
                    "bottom line", "in the end", "the takeaway", "so if you", "and that's", "that's the", "that's how",
                    "so remember", "the answer is", "the key is", "that changed"],
}

PL = {
    "stopwords": set("""a aby ale bo by być był była było były będzie co czy dla do gdy gdzie go i ich im jak jako je jego
        jej jest jestem jesteś jeszcze już ja ją które który która których ma mam mamy może można mnie mi my na nad nam nas
        nie nim no o od on ona one oni oraz po pod przez przy się są ta tak tam te tego tej ten to tu ty tym tylko też w we
        więc wszystko z za ze że żeby no tak jakby znaczy wiesz właśnie chyba trochę bardzo""".split()),
    "fillers": ["yyy", "eee", "eem", "hmm", "jakby", "wiesz", "znaczy", "no wiesz", "tak naprawdę", "w sumie", "generalnie",
                "po prostu", "że tak powiem", "tak jakby"],
    "backref_first": {"i", "ale", "więc", "bo", "czyli", "albo", "lub", "też", "także", "a", "zatem", "dlatego"},
    "pronoun_first": {"to", "on", "ona", "oni", "one", "tamto", "tego", "tym", "ten", "ta", "te", "tam"},
    "response_first": {"tak", "no", "dokładnie", "jasne", "pewnie", "właśnie", "nie", "zgadza", "racja", "okej", "mhm"},
    "backref_phrases": ["jak mówiłem", "jak mówiłam", "jak powiedziałeś", "jak powiedziałaś", "wracając do", "dlatego właśnie",
                        "i właśnie dlatego"],
    "hooks": ["najwięcej", "największy", "największa", "najważniejsze", "najgorsze", "najlepsze", "większość ludzi", "nikt",
              "nigdy", "zawsze", "sekret", "prawda jest", "problem", "błąd", "dlaczego", "wyobraź", "co jeśli", "mit",
              "zaskakujące", "nikt ci nie mówi", "jedna rzecz", "przestań", "jak to", "co się dzieje"],
    "conclusions": ["dlatego", "lekcja", "chodzi o to", "w skrócie", "podsumowując", "czyli", "więc jeśli", "kluczem jest",
                    "odpowiedź brzmi", "to zmieniło", "i to jest"],
}

LEXICONS = {"en": EN, "pl": PL}


def lexicon(language: str | None) -> dict:
    return LEXICONS.get((language or "en").split("-")[0].lower(), EN)
