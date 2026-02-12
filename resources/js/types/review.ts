export interface Review {
    id: number;
    author_name: string;
    author_phone: string | null;
    branch_name: string | null;
    rating: number;
    text: string | null;
    published_at: string | null;
}
